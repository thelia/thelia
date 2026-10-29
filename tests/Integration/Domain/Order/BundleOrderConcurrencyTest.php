<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Integration\Domain\Order;

use PHPUnit\Framework\Attributes\Group;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Thelia\Core\Event\Order\OrderProductEvent;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Bundle\BundleCatalogState;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Order\Exception\StockShortageException;
use Thelia\Domain\Order\Service\StockDecrementer;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\Product;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Integration\Domain\Catalog\Bundle\BuildsBundles;
use Thelia\Tests\Support\Order\PlacesBundleOrders;

/**
 * Two buyers ordering the same pack when one of its components has a single unit left:
 * one gets it, the other is refused, and the stock never goes below zero.
 *
 * Real parallelism is out of reach of a single PHPUnit process, so the interleaving is
 * staged on two genuinely distinct connections, as the delivery slot booking is: the order
 * under test runs on the connection of the test, in a real transaction, and the other
 * buyer is a second connection that takes its stock through the same conditional update
 * (StockDecrementer) at the instant the test chooses. The pre-check of the order has
 * already passed when that happens, so the only thing standing between the two buyers and
 * an oversold unit is the guard inside the transaction.
 *
 * The rows have to be committed for the second connection to see them, so this test runs
 * outside the transaction wrapper and deletes everything it wrote.
 */
#[Group('concurrency')]
final class BundleOrderConcurrencyTest extends ActionIntegrationTestCase
{
    use BuildsBundles;
    use PlacesBundleOrders;

    protected bool $useTransaction = false;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    private const PROCESS_TIMEOUT = 60;

    private ?ConnectionInterface $otherBuyer = null;

    /** @var array<int, array<int, resource>> the pipes of the processes started, by process */
    private array $pipes = [];

    /** @var list<Product> */
    private array $products = [];

    /** @var list<int> */
    private array $cartIds = [];

    /** @var list<int> */
    private array $customerIds = [];

    /** @var list<int> */
    private array $orderAddressIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check_available_stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        ConfigQuery::resetCache();

        $this->onManageStock(static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(true), 512);
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        try {
            $connection = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);

            while ($connection->inTransaction()) {
                $connection->rollBack();
            }

            $this->otherBuyer?->inTransaction() && $this->otherBuyer->rollBack();
            $this->deleteWhatTheTestCommitted();

            // The products went by a plain DELETE, which the model that keeps the state of the
            // catalogue never saw: read it again, or every later test believes the shop sells packs.
            ConfigQuery::resetCache();
            (new BundleCatalogState())->refresh();
        } finally {
            $this->otherBuyer = null;
            ConfigQuery::resetCache();
        }

        parent::tearDown();
    }

    public function testTheBuyerWhoLosesTheLastUnitOfAComponentIsRefusedAndNothingStaysTaken(): void
    {
        [$pack, $bike, $helmet, $items] = $this->packWithALastHelmet();
        [$customer, $currency, $lang] = $this->aBuyer();
        $this->customerIds[] = (int) $customer->getId();

        $cart = $this->aCart($customer, $currency);
        $this->cartIds[] = (int) $cart->getId();
        $this->addBundleLine($cart, $pack, [(int) $items['bike']->getId() => '1', (int) $items['helmet']->getId() => '1']);

        // The other buyer takes the last helmet, and its bike, after this order has checked the
        // stock and opened its transaction, and before it takes anything.
        $this->onManageStock(function (): void {
            $this->theOtherBuyerOrdersOnePack();
        }, 0);

        try {
            $this->placeOrder($cart, $customer, $currency, $lang);
            self::fail('The last helmet went to the other buyer: this order must be refused.');
        } catch (StockShortageException $shortage) {
            self::assertSame($helmet->getRef(), $shortage->productReference, 'The refusal names the component that ran out.');
        }

        self::assertSame(0.0, $this->committedStockOf($helmet), 'The last helmet was taken once, and the stock did not go below zero.');
        self::assertSame(4.0, $this->committedStockOf($bike), 'The bike this order had already taken went back with its rollback: only the other buyer took one.');
        self::assertSame(0, OrderQuery::create()->filterByCartId($cart->getId())->count(), 'A refused order leaves nothing behind.');
    }

    public function testAnOrderHoldingTheLastUnitMakesTheOtherBuyerWaitForItsOutcome(): void
    {
        [$pack, $bike, $helmet, $items] = $this->packWithALastHelmet();
        [$customer, $currency, $lang] = $this->aBuyer();
        $this->customerIds[] = (int) $customer->getId();

        $cart = $this->aCart($customer, $currency);
        $this->cartIds[] = (int) $cart->getId();
        $this->addBundleLine($cart, $pack, [(int) $items['bike']->getId() => '1', (int) $items['helmet']->getId() => '1']);

        $other = $this->otherBuyer();
        $other->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $waited = false;

        // The order has taken the helmet and has not committed: the other buyer cannot read
        // past it to a stock that still says "one left".
        $this->dispatcher->addListener(TheliaEvents::ORDER_PRODUCT_AFTER_CREATE, $listener = function (OrderProductEvent $event) use ($other, $helmet, &$waited): void {
            $line = OrderProductQuery::create()->findPk($event->getId());

            if (null === $line || $line->getProductSaleElementsId() !== $this->saleElementsIdOf($helmet)) {
                return;
            }

            $other->beginTransaction();

            try {
                $this->getService(StockDecrementer::class)->decrement($this->saleElementsIdOf($helmet), 1.0, true, false, $other);
            } catch (\PDOException $lockWait) {
                $waited = str_contains($lockWait->getMessage(), 'Lock wait timeout');
            } finally {
                $other->rollBack();
            }
        }, 0);
        $this->registeredListeners[] = [TheliaEvents::ORDER_PRODUCT_AFTER_CREATE, $listener];

        $order = $this->placeOrder($cart, $customer, $currency, $lang);

        self::assertTrue($waited, 'The other buyer must wait on the order that holds the helmet, not read past it.');
        self::assertNotNull($order->getId());
        self::assertSame(0.0, $this->committedStockOf($helmet));

        $other->beginTransaction();

        try {
            $this->getService(StockDecrementer::class)->decrement($this->saleElementsIdOf($helmet), 1.0, true, false, $other);
            self::fail('Once the order committed, no helmet is left for the other buyer.');
        } catch (TheliaProcessException) {
            self::assertSame(0.0, $this->committedStockOf($helmet), 'The refused decrement leaves the stock at zero.');
        } finally {
            $other->rollBack();
        }
    }

    public function testTwoOrdersOnePackAndOneUnitLeftOnlyTheFirstPasses(): void
    {
        [$pack, $bike, $helmet, $items] = $this->packWithALastHelmet();
        [$customer, $currency, $lang] = $this->aBuyer();
        $this->customerIds[] = (int) $customer->getId();

        $orders = [];

        foreach ([1, 2] as $buyer) {
            $cart = $this->aCart($customer, $currency);
            $this->cartIds[] = (int) $cart->getId();
            $this->addBundleLine($cart, $pack, [(int) $items['bike']->getId() => '1', (int) $items['helmet']->getId() => '1']);
            $orders[$buyer] = $cart;
        }

        $first = $this->placeOrder($orders[1], $customer, $currency, $lang);
        self::assertNotNull($first->getId());

        $this->expectException(StockShortageException::class);

        try {
            $this->placeOrder($orders[2], $customer, $currency, $lang);
        } finally {
            self::assertSame(0.0, $this->committedStockOf($helmet), 'No negative stock.');
            self::assertSame(4.0, $this->committedStockOf($bike));
            self::assertSame(0.0, $this->committedStockOf($pack), 'The pack never holds stock.');
        }
    }

    /**
     * Two real placements at once, each in a process of its own: this one holds the last
     * helmet in its open transaction when the other process places the same pack. The other
     * one passed the pre-check on the committed stock, then waits on the rows this one
     * locked; once this one commits, its guard finds no helmet left and it is refused, the
     * bike it had taken given back with its rollback.
     */
    public function testTwoPlacementsAtOnceInTwoProcessesOnlyOneGetsTheLastUnit(): void
    {
        [$pack, $bike, $helmet, $items] = $this->packWithALastHelmet();
        [$customer, $currency, $lang] = $this->aBuyer();
        $this->customerIds[] = (int) $customer->getId();

        $carts = [];

        foreach (['this', 'other'] as $buyer) {
            $carts[$buyer] = $this->aCart($customer, $currency);
            $this->cartIds[] = (int) $carts[$buyer]->getId();
            $this->addBundleLine($carts[$buyer], $pack, [(int) $items['bike']->getId() => '1', (int) $items['helmet']->getId() => '1']);
        }

        $otherAddresses = [(int) $this->factory->orderAddress($this->buyerCountry())->getId(), (int) $this->factory->orderAddress($this->buyerCountry())->getId()];
        $this->orderAddressIds = [...$this->orderAddressIds, ...$otherAddresses];
        $process = null;

        // Once this order took its stock, and before it commits.
        $this->dispatcher->addListener(TheliaEvents::ORDER_PRODUCT_AFTER_CREATE, $listener = function () use (&$process, $carts, $customer, $otherAddresses): void {
            if (null !== $process) {
                return;
            }

            $process = $this->placeInAnotherProcess((int) $carts['other']->getId(), (int) $customer->getId(), ...$otherAddresses);
            $this->waitUntilTheOtherProcessWaitsOnTheStock($process);
        }, 0);
        $this->registeredListeners[] = [TheliaEvents::ORDER_PRODUCT_AFTER_CREATE, $listener];

        $order = $this->placeOrder($carts['this'], $customer, $currency, $lang);
        self::assertNotNull($process, 'The other process was started while this order held the stock.');
        $outcome = $this->waitFor($process);

        self::assertNotNull($order->getId());
        self::assertSame(['outcome' => 'refused', 'orderId' => null, 'reference' => $helmet->getRef()], $outcome, 'The other buyer is refused, naming the helmet.');
        self::assertSame(0.0, $this->committedStockOf($helmet), 'The last helmet was taken once, and the stock never went below zero.');
        self::assertSame(4.0, $this->committedStockOf($bike), 'The bike the other process took went back with its rollback.');
        self::assertSame(0, OrderQuery::create()->filterByCartId($carts['other']->getId())->count(), 'The refused order left nothing behind.');
        self::assertSame(1, OrderQuery::create()->filterByCartId($carts['this']->getId())->count());
    }

    /**
     * @return resource
     */
    private function placeInAnotherProcess(int $cartId, int $customerId, int $deliveryAddressId, int $invoiceAddressId)
    {
        $process = proc_open(
            [\PHP_BINARY, \dirname(__DIR__, 3).'/Support/Order/place-order-in-another-process.php', (string) $cartId, (string) $customerId, (string) $deliveryAddressId, (string) $invoiceAddressId],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        $this->pipes[(int) $process] = $pipes;

        return $process;
    }

    /**
     * The other process is blocked on an update of a stock row this order holds: read off
     * the processes of the server, not guessed from a sleep. A statement seen waiting twice
     * in a row is a wait, not a statement caught while running.
     *
     * @param resource $process
     */
    private function waitUntilTheOtherProcessWaitsOnTheStock($process): void
    {
        $ownThreads = [
            (int) Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME)->query('SELECT CONNECTION_ID()')->fetchColumn(),
            (int) $this->otherBuyer()->query('SELECT CONNECTION_ID()')->fetchColumn(),
        ];
        $waiting = $this->otherBuyer()->prepare(
            "SELECT CONCAT(ID, ':', INFO) FROM information_schema.PROCESSLIST WHERE DB = DATABASE() AND COMMAND IN ('Query', 'Execute') AND INFO LIKE 'UPDATE `product_sale_elements`%' AND ID NOT IN (".implode(',', $ownThreads).')',
        );
        $deadline = microtime(true) + self::PROCESS_TIMEOUT;
        $seen = [];

        do {
            if (!proc_get_status($process)['running']) {
                self::fail('The other process ended before waiting: '.stream_get_contents($this->pipes[(int) $process][1]).stream_get_contents($this->pipes[(int) $process][2]));
            }

            $waiting->execute();
            $threads = $waiting->fetchAll(\PDO::FETCH_COLUMN);
            $waiting->closeCursor();

            if ([] !== array_intersect($threads, $seen)) {
                return;
            }

            $seen = $threads;
            usleep(200000);
        } while (microtime(true) < $deadline);

        self::fail('The other process never waited on the stock.');
    }

    /**
     * @param resource $process
     *
     * @return array{outcome: string, orderId: int|null, reference: string|null}
     */
    private function waitFor($process): array
    {
        [1 => $stdout, 2 => $stderr] = $this->pipes[(int) $process];
        unset($this->pipes[(int) $process]);
        $output = (string) stream_get_contents($stdout);
        $errors = (string) stream_get_contents($stderr);
        fclose($stdout);
        fclose($stderr);

        self::assertSame(0, proc_close($process), 'The other process failed: '.$errors.$output);

        // The JSON is the last thing the process prints, after whatever booting it wrote.
        return json_decode(substr($output, (int) strrpos($output, '{"outcome"')), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{0: Product, 1: Product, 2: Product, 3: array{bike: \Thelia\Model\ProductBundleItem, helmet: \Thelia\Model\ProductBundleItem}}
     */
    private function packWithALastHelmet(): array
    {
        $service = $this->getService(BundleCompositionService::class);
        $bike = $this->component($this->factory, '500', 5);
        $helmet = $this->component($this->factory, '30', 1);
        $pack = $this->bundleProduct($this->factory, $service);
        $this->products = [$bike, $helmet, $pack];

        return [$pack, $bike, $helmet, [
            'bike' => $service->addItem((int) $pack->getId(), $this->saleElementsIdOf($bike), '1'),
            'helmet' => $service->addItem((int) $pack->getId(), $this->saleElementsIdOf($helmet), '1'),
        ]];
    }

    /**
     * The other buyer's order, as far as the stock is concerned: its own connection, its own
     * transaction, the same conditional updates, committed.
     */
    private function theOtherBuyerOrdersOnePack(): void
    {
        $other = $this->otherBuyer();
        $decrementer = $this->getService(StockDecrementer::class);
        $other->beginTransaction();

        try {
            [$bike, $helmet] = $this->products;
            $decrementer->decrement($this->saleElementsIdOf($bike), 1.0, true, false, $other);
            $decrementer->decrement($this->saleElementsIdOf($helmet), 1.0, true, false, $other);
            $other->commit();
        } catch (\Throwable $failure) {
            $other->rollBack();

            throw $failure;
        }
    }

    private function committedStockOf(Product $product): float
    {
        $statement = $this->otherBuyer()->prepare('SELECT `quantity` FROM `product_sale_elements` WHERE `product_id` = :id');
        $statement->execute([':id' => $product->getId()]);

        return (float) $statement->fetchColumn();
    }

    private function onManageStock(callable $listener, int $priority): void
    {
        $eventName = TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque');

        $this->dispatcher->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    /**
     * A connection of its own, opened from the configuration Propel already holds: Propel
     * hands out one connection per datasource, and two buyers on it would share a transaction.
     */
    private function otherBuyer(): ConnectionInterface
    {
        if (null !== $this->otherBuyer) {
            return $this->otherBuyer;
        }

        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(ProductSaleElementsTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        return $this->otherBuyer = ConnectionFactory::create(
            $connectionManager->getConfiguration(),
            $serviceContainer->getAdapter(ProductSaleElementsTableMap::DATABASE_NAME),
        );
    }

    /**
     * Everything the test committed, in foreign key order: the orders it placed with their
     * lines, the carts, the addresses, the buyer, the products and their categories.
     */
    private function deleteWhatTheTestCommitted(): void
    {
        $connection = $this->otherBuyer();

        if ([] !== $this->cartIds) {
            $in = implode(',', array_map('intval', $this->cartIds));

            $addresses = $connection->query("SELECT invoice_order_address_id, delivery_order_address_id FROM `order` WHERE cart_id IN ($in)")->fetchAll(\PDO::FETCH_NUM);
            foreach ($addresses as $pair) {
                $this->orderAddressIds = [...$this->orderAddressIds, (int) $pair[0], (int) $pair[1]];
            }

            $connection->exec("DELETE FROM `order` WHERE cart_id IN ($in)");
            $connection->exec("DELETE FROM cart WHERE id IN ($in)");
        }

        // Also those of an order that was refused: written before it, they belong to no order.
        $this->orderAddressIds = [...$this->orderAddressIds, ...$this->orderAddressIdsOfPlacements];

        if ([] !== $this->orderAddressIds) {
            $connection->exec('DELETE FROM order_address WHERE id IN ('.implode(',', array_unique($this->orderAddressIds)).')');
        }

        if ([] !== $this->customerIds) {
            $connection->exec('DELETE FROM customer WHERE id IN ('.implode(',', $this->customerIds).')');
        }

        if ([] !== $this->products) {
            $in = implode(',', array_map(static fn (Product $product): int => (int) $product->getId(), $this->products));
            $categoryIds = $connection->query("SELECT DISTINCT category_id FROM product_category WHERE product_id IN ($in)")->fetchAll(\PDO::FETCH_COLUMN);

            $connection->exec("DELETE FROM product WHERE id IN ($in)");

            if ([] !== $categoryIds) {
                $connection->exec('DELETE FROM category WHERE id IN ('.implode(',', array_map('intval', $categoryIds)).')');
            }
        }
    }
}
