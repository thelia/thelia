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

namespace Thelia\Tests\Integration\Domain\Catalog\Stock;

use PHPUnit\Framework\Attributes\Group;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Stock\StockRowLocker;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\OrderQuery;
use Thelia\Model\Product;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\PlacesBundleOrders;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * Holds and placements of the last unit, at the same moment, in two processes: the competing
 * request is a second PHP process with its own kernel and connection, started while this one
 * holds the stock row. The fixtures are committed, and deleted by hand.
 *
 * Physical 1: two carts holding it at once, one holds it and the other is refused; two buyers
 * paying at once, one order is placed and the other refused, and the stock never goes below 0.
 */
#[Group('concurrency')]
final class StockReservationConcurrencyTest extends ActionIntegrationTestCase
{
    use PlacesBundleOrders;

    protected bool $useTransaction = false;

    private const PROCESS_TIMEOUT = 60;

    private ?ConnectionInterface $otherSession = null;

    /** @var array<int, array<int, resource>> */
    private array $pipes = [];

    /** @var list<Product> */
    private array $products = [];

    /** @var list<int> */
    private array $cartIds = [];

    /** @var list<int> */
    private array $customerIds = [];

    /** @var list<int> */
    private array $orderAddressIds = [];

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    /** @var array<string, string|null> the settings as they were before the test */
    private array $settingsBefore = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['check-available-stock', 'allow_negative_stock', StockSettings::RESERVATION_ENABLED, StockSettings::RESERVATION_SCOPE, StockSettings::RESERVATION_DURATION] as $name) {
            $this->settingsBefore[$name] = ConfigQuery::create()->findOneByName($name)?->getValue();
        }

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'checkout');
        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '15');
        ConfigQuery::resetCache();

        $eventName = TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque');
        $listener = static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(true);
        $this->dispatcher->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher->removeListener($eventName, $listener);
        }

        try {
            $connection = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);

            while ($connection->inTransaction()) {
                $connection->rollBack();
            }

            $this->deleteWhatTheTestCommitted();
        } finally {
            foreach ($this->settingsBefore as $name => $value) {
                ConfigQuery::write($name, $value ?? '');
            }

            ConfigQuery::resetCache();
            $this->otherSession = null;
        }

        parent::tearDown();
    }

    /**
     * This process holds the stock row of the last unit, and holds it for its cart, while the
     * other process holds the same unit for another cart: the other one waits, then reads the
     * reservation committed meanwhile and is refused.
     */
    public function testTwoHoldsOfTheLastUnitAtOnceOnlyOneHoldsIt(): void
    {
        $product = $this->committedProduct(1);
        [$cartA, $lineA] = $this->committedCart($product, 1);
        [$cartB, $lineB] = $this->committedCart($product, 1);
        $connection = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);

        $connection->beginTransaction();
        $this->getService(StockRowLocker::class)->lock([(int) $product->getDefaultSaleElements()->getId()], $connection);
        $process = $this->inAnotherProcess('hold', (int) $cartB->getId());
        $this->waitUntilTheOtherProcessWaitsOnTheStock($process);
        // This one holds in the transaction that keeps the row locked, which a caller never does.
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn($connection);

        try {
            $this->getService(CheckoutFacade::class)->holdStock($cartA);
        } finally {
            $this->getService(TestCallerTransaction::class)->reset();
        }

        $connection->commit();

        $outcome = $this->waitFor($process);

        self::assertSame('refused', $outcome['outcome'], 'The other cart waited for this hold, then found the unit held.');
        self::assertSame($product->getRef(), $outcome['reference']);
        self::assertStringContainsString('is no longer available', (string) $outcome['message']);
        self::assertTrue($this->isHeld($lineA));
        self::assertFalse($this->isHeld($lineB));
    }

    /**
     * An order being placed holds the stock row while a cart tries to hold the unit: the hold
     * waits for the order, then finds nothing left.
     */
    public function testAHoldDuringThePlacementOfTheLastUnitWaitsAndIsRefused(): void
    {
        $product = $this->committedProduct(1);
        [$customer, $currency, $lang] = $this->committedBuyer();
        $cartB = $this->aCart($customer, $currency);
        $this->cartIds[] = (int) $cartB->getId();
        $this->factory->cartItem($cartB, $product, null, ['quantity' => 1.0]);
        [$cartA, $lineA] = $this->committedCart($product, 1);
        $process = null;

        $this->duringThePlacement(function () use (&$process, $cartA): void {
            $process ??= $this->inAnotherProcess('hold', (int) $cartA->getId());
            $this->waitUntilTheOtherProcessWaitsOnTheStock($process);
        });

        $this->placeOrder($cartB, $customer, $currency, $lang);
        self::assertNotNull($process);
        $outcome = $this->waitFor($process);

        self::assertSame('refused', $outcome['outcome']);
        self::assertSame($product->getRef(), $outcome['reference']);
        self::assertFalse($this->isHeld($lineA));
        self::assertSame(0.0, $this->committedStockOf($product));
    }

    /**
     * Two buyers pay for the last unit at once, neither of them holding it any more: the other
     * process passes the check made before its transaction, then waits on the stock row this
     * order locked; once this one commits, its guard finds nothing left and it is refused,
     * naming the product. The stock never goes below zero.
     */
    public function testTwoPaymentsOfTheLastUnitAtOncePlaceOneOrder(): void
    {
        $product = $this->committedProduct(1);
        [$customer, $currency, $lang] = $this->committedBuyer();
        $carts = [];

        foreach (['this', 'other'] as $buyer) {
            $carts[$buyer] = $this->aCart($customer, $currency);
            $this->cartIds[] = (int) $carts[$buyer]->getId();
            $this->factory->cartItem($carts[$buyer], $product, null, ['quantity' => 1.0]);
        }

        $addresses = $this->committedOrderAddresses();
        $process = null;

        $this->duringThePlacement(function () use (&$process, $carts, $customer, $addresses): void {
            $process ??= $this->inAnotherProcess('place', (int) $carts['other']->getId(), (int) $customer->getId(), ...$addresses);
            $this->waitUntilTheOtherProcessWaitsOnTheStock($process);
        });

        $order = $this->placeOrder($carts['this'], $customer, $currency, $lang);
        self::assertNotNull($process);
        $outcome = $this->waitFor($process);

        self::assertNotNull($order->getId());
        self::assertSame('refused', $outcome['outcome']);
        self::assertSame($product->getRef(), $outcome['reference']);
        self::assertStringContainsString($product->getRef(), (string) $outcome['message']);
        self::assertSame(0.0, $this->committedStockOf($product), 'Taken once, never below zero.');
        self::assertSame(0, OrderQuery::create()->filterByCartId($carts['other']->getId())->count());
    }

    /**
     * The buyer holding the last unit pays while another buyer pays for it too: the other one is
     * refused, whichever commits first, and the holder's order is placed.
     */
    public function testTheOtherBuyerPayingForTheUnitAnotherCartHoldsIsRefused(): void
    {
        $product = $this->committedProduct(1);
        [$customer, $currency, $lang] = $this->committedBuyer();
        $holder = $this->aCart($customer, $currency);
        $other = $this->aCart($customer, $currency);
        $this->cartIds = [...$this->cartIds, (int) $holder->getId(), (int) $other->getId()];
        $this->factory->cartItem($other, $product, null, ['quantity' => 1.0]);
        $this->factory->cartItem($holder, $product, null, ['quantity' => 1.0]);
        $this->getService(CheckoutFacade::class)->holdStock($holder);
        $addresses = $this->committedOrderAddresses();
        $process = null;

        $this->duringThePlacement(function () use (&$process, $other, $customer, $addresses): void {
            $process ??= $this->inAnotherProcess('place', (int) $other->getId(), (int) $customer->getId(), ...$addresses);
            $this->waitUntilTheOtherProcessWaitsOnTheStock($process, mayEnd: true);
        });

        $order = $this->placeOrder($holder, $customer, $currency, $lang);
        self::assertNotNull($process);
        $outcome = $this->waitFor($process);

        self::assertNotNull($order->getId());
        self::assertSame('refused', $outcome['outcome']);
        self::assertSame($product->getRef(), $outcome['reference']);
        self::assertSame(0.0, $this->committedStockOf($product));
    }

    /**
     * A listener of the stock levels reads the stock from a connection of its own, as a mailer or
     * a worker would: the order is committed when it hears of it, so it reads what the order left.
     */
    public function testTheListenersOfTheStockReadWhatTheCommittedOrderLeft(): void
    {
        $product = $this->committedProduct(22);
        [$customer, $currency, $lang] = $this->committedBuyer();
        $cart = $this->aCart($customer, $currency);
        $this->cartIds[] = (int) $cart->getId();
        $this->factory->cartItem($cart, $product, null, ['quantity' => 3.0]);
        $readByTheListener = [];
        $listener = function () use (&$readByTheListener, $product): void {
            $readByTheListener[] = $this->committedStockOf($product);
        };
        $this->dispatcher->addListener(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, $listener, 0);
        $this->registeredListeners[] = [TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, $listener];

        $this->placeOrder($cart, $customer, $currency, $lang);

        self::assertSame([19.0], $readByTheListener, 'Heard once, after the commit.');
    }

    private function duringThePlacement(callable $then): void
    {
        // Once this order took its stock, and before it commits.
        $this->dispatcher->addListener(TheliaEvents::ORDER_PRODUCT_AFTER_CREATE, $listener = static function () use ($then): void {
            $then();
        }, 0);
        $this->registeredListeners[] = [TheliaEvents::ORDER_PRODUCT_AFTER_CREATE, $listener];
    }

    /**
     * @return resource
     */
    private function inAnotherProcess(string $mode, int $cartId, int ...$arguments)
    {
        $process = proc_open(
            [\PHP_BINARY, \dirname(__DIR__, 4).'/Support/Order/reserve-or-place-in-another-process.php', $mode, (string) $cartId, ...array_map('strval', $arguments)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        $this->pipes[(int) $process] = $pipes;

        return $process;
    }

    /**
     * The other process is blocked on the lock of a stock row this one holds: read off the
     * processes of the server, a locking statement seen twice in a row is a wait. With $mayEnd,
     * a process that ends without waiting (refused before its transaction) is no failure.
     *
     * @param resource $process
     */
    private function waitUntilTheOtherProcessWaitsOnTheStock($process, bool $mayEnd = false): void
    {
        $ownThreads = [
            (int) Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME)->query('SELECT CONNECTION_ID()')->fetchColumn(),
            (int) $this->otherSession()->query('SELECT CONNECTION_ID()')->fetchColumn(),
        ];
        $waiting = $this->otherSession()->prepare(
            "SELECT CONCAT(ID, ':', INFO) FROM information_schema.PROCESSLIST WHERE DB = DATABASE() AND COMMAND IN ('Query', 'Execute') AND INFO LIKE '%product_sale_elements%' AND (INFO LIKE '%FOR UPDATE%' OR INFO LIKE 'UPDATE%') AND ID NOT IN (".implode(',', $ownThreads).')',
        );
        $deadline = microtime(true) + self::PROCESS_TIMEOUT;
        $seen = [];

        do {
            if (!proc_get_status($process)['running']) {
                if ($mayEnd) {
                    return;
                }

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
     * @return array{outcome: string, reference: string|null, message: string|null}
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

    private function committedProduct(int $quantity): Product
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $product->getDefaultSaleElements()->setQuantity($quantity)->save();
        $this->products[] = $product;

        return $product;
    }

    /**
     * @return array{0: Cart, 1: CartItem}
     */
    private function committedCart(Product $product, int $quantity): array
    {
        $cart = $this->factory->cart(null, ['currency' => $this->factory->currency()]);
        $this->cartIds[] = (int) $cart->getId();

        return [$cart, $this->factory->cartItem($cart, $product, null, ['quantity' => (float) $quantity])];
    }

    /**
     * @return array{0: Customer, 1: \Thelia\Model\Currency, 2: \Thelia\Model\Lang}
     */
    private function committedBuyer(): array
    {
        $buyer = $this->aBuyer();
        $this->customerIds[] = (int) $buyer[0]->getId();

        return $buyer;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function committedOrderAddresses(): array
    {
        $addresses = [(int) $this->factory->orderAddress($this->buyerCountry())->getId(), (int) $this->factory->orderAddress($this->buyerCountry())->getId()];
        $this->orderAddressIds = [...$this->orderAddressIds, ...$addresses];

        return $addresses;
    }

    private function isHeld(CartItem $line): bool
    {
        return 1 === (int) $this->otherSession()->query('SELECT COALESCE(reserved_until > NOW(), 0) FROM cart_item WHERE id = '.(int) $line->getId())->fetchColumn();
    }

    private function committedStockOf(Product $product): float
    {
        return (float) $this->otherSession()->query('SELECT quantity FROM product_sale_elements WHERE id = '.(int) $product->getDefaultSaleElements()->getId())->fetchColumn();
    }

    /**
     * A connection of its own, opened from the configuration Propel already holds: it reads what
     * is committed, and only that.
     */
    private function otherSession(): ConnectionInterface
    {
        if (null !== $this->otherSession) {
            return $this->otherSession;
        }

        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(ProductSaleElementsTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        return $this->otherSession = ConnectionFactory::create(
            $connectionManager->getConfiguration(),
            $serviceContainer->getAdapter(ProductSaleElementsTableMap::DATABASE_NAME),
        );
    }

    /**
     * Everything the test committed, in foreign key order.
     */
    private function deleteWhatTheTestCommitted(): void
    {
        $connection = $this->otherSession();

        if ([] !== $this->cartIds) {
            $in = implode(',', array_map('intval', $this->cartIds));

            foreach ($connection->query("SELECT invoice_order_address_id, delivery_order_address_id FROM `order` WHERE cart_id IN ($in)")->fetchAll(\PDO::FETCH_NUM) as $pair) {
                $this->orderAddressIds = [...$this->orderAddressIds, (int) $pair[0], (int) $pair[1]];
            }

            $connection->exec("DELETE FROM `order` WHERE cart_id IN ($in)");
            $connection->exec("DELETE FROM cart WHERE id IN ($in)");
        }

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
