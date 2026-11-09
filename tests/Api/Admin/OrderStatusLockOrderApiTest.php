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

namespace Thelia\Tests\Api\Admin;

use PHPUnit\Framework\Attributes\Group;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ApiTestCase;

/**
 * Two requests that change the status of the same order at the same moment, the second one a
 * process of its own: a payment coming back from a module (the status event, as the back office
 * and the payment modules dispatch it) and the admin API. Every path takes its locks in the same
 * order, the row of the order and then the stock rows, so that neither waits for the other for
 * ever; and the tie the database would still break is a refusal the caller can retry, never a 500.
 *
 * The fixtures are committed, and deleted by hand.
 */
#[Group('concurrency')]
final class OrderStatusLockOrderApiTest extends ApiTestCase
{
    protected bool $useTransaction = false;

    private const PROCESS_TIMEOUT = 60;

    /** @var array<string, string|null> */
    private array $settingsBefore = [];

    /** @var array<string, int|null> */
    private array $committed = [];

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    /** @var list<string> */
    private array $markerFiles = [];

    /** @var array<int, array<int, resource>> */
    private array $pipes = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['check-available-stock', 'allow_negative_stock', StockSettings::RESERVATION_ENABLED, StockSettings::RESERVATION_SCOPE, StockSettings::RESERVATION_HAS_HELD] as $name) {
            $this->settingsBefore[$name] = ConfigQuery::create()->findOneByName($name)?->getValue();
        }

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'checkout');
        ConfigQuery::resetCache();

        // The stock of the order is left for its payment: the payment takes it.
        $listener = static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(false);
        $eventName = TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque');
        $this->getService(EventDispatcherInterface::class)->addListener($eventName, $listener, 512);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    protected function tearDown(): void
    {
        $dispatcher = $this->getService(EventDispatcherInterface::class);

        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $dispatcher->removeListener($eventName, $listener);
        }

        $connection = Propel::getWriteConnection(OrderTableMap::DATABASE_NAME);

        while ($connection->inTransaction()) {
            $connection->rollBack();
        }

        foreach ($this->pipes as $pipes) {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
        }
        $this->pipes = [];

        foreach ($this->markerFiles as $marker) {
            foreach ([$marker, $marker.'.go'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        try {
            $this->deleteTheCommittedFixtures($connection);
        } finally {
            foreach ($this->settingsBefore as $name => $value) {
                ConfigQuery::write($name, $value ?? '');
            }

            ConfigQuery::resetCache();
        }

        parent::tearDown();
    }

    public function testAPaymentReturnAndAnAdminPaymentOnTheSameOrderWaitForEachOtherInTheSameOrder(): void
    {
        [$orderId, $combinationId, $lineId, $token] = $this->unpaidOrderWithItsStockLeftForThePayment();
        $paid = $this->statusId(OrderStatus::CODE_PAID);

        // The payment return holds what it locked and waits before it writes the order; the admin
        // API asks for the same order meanwhile. Locks taken in a different order would be a
        // deadlock, and one of the two requests would be broken by the database.
        $process = $this->startTheOtherProcess('pay', $orderId, $paid);
        $this->waitForTheMarker($process);

        $response = $this->jsonRequest('PATCH', '/api/admin/orders/'.$orderId, [
            'orderStatus' => '/api/admin/order_statutes/'.$paid,
            'orderProducts' => ['/api/admin/order_products/'.$lineId],
        ], $token, 'merge-patch+json');

        $other = $this->waitFor($process);

        self::assertSame(['outcome' => 'done', 'failure' => null], $other, 'The payment return went through.');
        self::assertSame(200, $response->getStatusCode(), 'The admin API found the order paid: '.$response->getContent());
        self::assertSame(4.0, $this->stockOf($combinationId), 'The unit was taken once.');
        self::assertSame($paid, $this->statusOf($orderId));
    }

    public function testAPaymentConfirmedTwiceAfterTheAdminPaidTheOrderTakesTheStockOnce(): void
    {
        [$orderId, $combinationId, $lineId, $token] = $this->unpaidOrderWithItsStockLeftForThePayment();
        $paid = $this->statusId(OrderStatus::CODE_PAID);

        // The notification of the server and the return of the browser both read the order unpaid;
        // the admin API pays it meanwhile, and the confirmations come after, each with the order
        // as it was read. What is read under the lock is what counts: the order is paid already.
        $process = $this->startTheOtherProcess('pay-twice', $orderId, $paid, 60000);
        $this->waitForTheMarker($process);

        $response = $this->jsonRequest('PATCH', '/api/admin/orders/'.$orderId, [
            'orderStatus' => '/api/admin/order_statutes/'.$paid,
            'orderProducts' => ['/api/admin/order_products/'.$lineId],
        ], $token, 'merge-patch+json');
        self::assertSame(200, $response->getStatusCode(), 'The admin API paid the order: '.$response->getContent());
        self::assertSame(4.0, $this->stockOf($combinationId), 'The admin API took the unit.');

        file_put_contents(end($this->markerFiles).'.go', 'go');
        $other = $this->waitFor($process);

        self::assertSame('done', $other['outcome'], 'The confirmations of an order paid already are no failure: '.json_encode($other));
        self::assertSame(4.0, $this->stockOf($combinationId), 'The unit was taken once.');
        self::assertSame(0, $other['stockLevelsTold'], 'No stock level is told for what the confirmations did not change.');
        self::assertSame($paid, $this->statusOf($orderId));
    }

    public function testATieTheDatabaseBreaksAgainstTheAdminApiIsARefusalToRetry(): void
    {
        [$orderId, $combinationId, $lineId, $token] = $this->unpaidOrderWithItsStockLeftForThePayment();
        $paid = $this->statusId(OrderStatus::CODE_PAID);

        // Another writer that takes the locks the other way round (the stock row, then the order),
        // and has written more than the admin API has when the tie is broken.
        $process = $this->startTheOtherProcess('wrong-order', $orderId, $combinationId);
        $this->waitForTheMarker($process);

        $response = $this->jsonRequest('PATCH', '/api/admin/orders/'.$orderId, [
            'orderStatus' => '/api/admin/order_statutes/'.$paid,
            'orderProducts' => ['/api/admin/order_products/'.$lineId],
        ], $token, 'merge-patch+json');

        $other = $this->waitFor($process);

        self::assertSame('done', $other['outcome'], 'The heavier transaction was kept: '.json_encode($other));
        self::assertSame(409, $response->getStatusCode(), 'A broken tie is a conflict, not a failure: '.$response->getContent());
        self::assertStringContainsString('Please try again', (string) $response->getContent());
        self::assertSame(5.0, $this->stockOf($combinationId), 'Nothing of the refused payment stands.');
        self::assertNotSame($paid, $this->statusOf($orderId));
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: string} the order, its sale element, its line, the token of an admin
     */
    private function unpaidOrderWithItsStockLeftForThePayment(): array
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $this->committed['category'] = (int) $category->getId();
        $product = $factory->product($category, $factory->taxRule(), $factory->currency());
        $this->committed['product'] = (int) $product->getId();
        $combination = $factory->productSaleElement($product, ['quantity' => 5]);

        $order = $factory->order();
        $this->committed['order'] = (int) $order->getId();
        $line = $factory->orderProduct($order, [
            'productSaleElementsId' => $combination->getId(),
            'productSaleElementsRef' => $combination->getRef(),
            'productRef' => $product->getRef(),
            'quantity' => 1.0,
        ]);
        (new OrderProductTax())->setOrderProductId($line->getId())->setTitle('VAT')->setAmount('2')->save();

        $admin = $factory->admin(['password' => 'password']);
        $this->committed['admin'] = (int) $admin->getId();

        return [(int) $order->getId(), (int) $combination->getId(), (int) $line->getId(), $this->authenticateAsAdmin($admin)];
    }

    /**
     * @return resource
     */
    private function startTheOtherProcess(string $mode, int $orderId, int $second, int $pause = 3000)
    {
        $marker = \dirname(__DIR__, 3).'/var/status-lock-order-'.bin2hex(random_bytes(6)).'.marker';
        $this->markerFiles[] = $marker;

        $process = proc_open(
            [\PHP_BINARY, \dirname(__DIR__, 2).'/Support/Order/change-status-in-another-process.php', $mode, (string) $orderId, (string) $second, $marker, (string) $pause],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        $this->pipes[(int) $process] = $pipes;

        return $process;
    }

    /**
     * @param resource $process
     */
    private function waitForTheMarker($process): void
    {
        $marker = end($this->markerFiles);
        $deadline = microtime(true) + self::PROCESS_TIMEOUT;

        while (!is_file($marker)) {
            if (!proc_get_status($process)['running']) {
                self::fail('The other process ended before holding its locks: '.stream_get_contents($this->pipes[(int) $process][2]).stream_get_contents($this->pipes[(int) $process][1]));
            }

            if (microtime(true) > $deadline) {
                self::fail('The other process never held its locks.');
            }

            usleep(50000);
        }
    }

    /**
     * @param resource $process
     *
     * @return array{outcome: string, failure: string|null, stockLevelsTold?: int}
     */
    private function waitFor($process): array
    {
        [1 => $stdout, 2 => $stderr] = $this->pipes[(int) $process];
        unset($this->pipes[(int) $process]);
        $output = (string) stream_get_contents($stdout);
        $errors = (string) stream_get_contents($stderr);
        fclose($stdout);
        fclose($stderr);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, 'The other process failed: '.$errors.$output);

        // The JSON is the last thing the process prints, after whatever booting it wrote.
        return json_decode(substr($output, (int) strrpos($output, '{"outcome"')), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function statusId(string $code): int
    {
        return (int) OrderStatusQuery::create()->findOneByCode($code)?->getId();
    }

    private function statusOf(int $orderId): int
    {
        $statement = Propel::getConnection()->prepare('SELECT `status_id` FROM `order` WHERE `id` = ?');
        $statement->execute([$orderId]);

        return (int) $statement->fetchColumn();
    }

    private function stockOf(int $combinationId): float
    {
        $statement = Propel::getConnection()->prepare('SELECT `quantity` FROM `product_sale_elements` WHERE `id` = ?');
        $statement->execute([$combinationId]);

        return (float) $statement->fetchColumn();
    }

    private function deleteTheCommittedFixtures(ConnectionInterface $connection): void
    {
        $connection->exec("DELETE FROM `config` WHERE `name` LIKE 'status-lock-weight-%'");

        if (isset($this->committed['order'])) {
            $read = $connection->prepare('SELECT customer_id, cart_id, invoice_order_address_id, delivery_order_address_id FROM `order` WHERE id = ?');
            $read->execute([$this->committed['order']]);
            $order = $read->fetch(\PDO::FETCH_ASSOC);
            $read->closeCursor();

            // The lines, their taxes, the versions and the history of the order go with it.
            $connection->prepare('DELETE FROM `order` WHERE id = ?')->execute([$this->committed['order']]);

            if (\is_array($order)) {
                $connection->prepare('DELETE FROM cart WHERE id = ?')->execute([$order['cart_id']]);
                $connection->prepare('DELETE FROM order_address WHERE id IN (?, ?)')->execute([$order['invoice_order_address_id'], $order['delivery_order_address_id']]);
                $connection->prepare('DELETE FROM customer WHERE id = ?')->execute([$order['customer_id']]);
            }
        }

        foreach (['admin' => 'admin', 'product' => 'product', 'category' => 'category'] as $key => $table) {
            if (isset($this->committed[$key])) {
                $connection->prepare('DELETE FROM `'.$table.'` WHERE id = ?')->execute([$this->committed[$key]]);
            }
        }

        $this->committed = [];
    }
}
