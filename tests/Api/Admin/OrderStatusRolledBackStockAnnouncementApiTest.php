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

use Propel\Runtime\Propel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\ProductSaleElement\LowStockReachedEvent;
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\ProductSaleElement\StockLevelChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * A payment written through `PATCH /api/admin/orders/{id}` is committed by the core with the stock it
 * takes, as from the back office: the admin API holds no transaction around the status event, whose
 * listeners call payment modules and write the payment journal, which a rollback would leave behind.
 * A listener that fails after the core leaves the status and the stock as the core committed them,
 * and what the stock went through is told once.
 *
 * Nothing is rolled back by the test transaction: the fixtures are committed, and deleted by hand.
 */
final class OrderStatusRolledBackStockAnnouncementApiTest extends ApiTestCase
{
    protected bool $useTransaction = false;

    /** @var list<StockLevelChange> */
    private array $changes = [];

    /** @var list<int> */
    private array $lowStock = [];

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    /** @var array<string, string|null> */
    private array $settingsBefore = [];

    /** @var array{order?: int, admin?: int, product?: int, category?: int} */
    private array $committed = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['check-available-stock', 'allow_negative_stock', StockSettings::RESERVATION_ENABLED] as $name) {
            $this->settingsBefore[$name] = ConfigQuery::create()->findOneByName($name)?->getValue();
        }

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '0');
        ConfigQuery::resetCache();

        $this->listen(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, function (StockLevelChangedEvent $event): void {
            $this->changes = [...$this->changes, ...$event->getChanges()];
        });
        $this->listen(TheliaEvents::PRODUCT_SALE_ELEMENTS_LOW_STOCK_REACHED, function (LowStockReachedEvent $event): void {
            $this->lowStock[] = $event->getProductSaleElementsId();
        });
        $this->listen(
            TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque'),
            static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(false),
            512,
        );

        $this->getService(TestCallerTransaction::class)->reset();
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

    public function testAPaymentALaterListenerFailsAfterStaysWrittenAndIsToldOnce(): void
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $this->committed['category'] = (int) $category->getId();
        $product = $factory->product($category, $factory->taxRule(), $factory->currency());
        $this->committed['product'] = (int) $product->getId();
        $combination = $factory->productSaleElement($product, ['quantity' => 5]);
        $combination->setLowStockThreshold(4)->save();

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
        $token = $this->authenticateAsAdmin($admin);

        $this->listen(TheliaEvents::ORDER_UPDATE_STATUS, static function (): void {
            throw new \RuntimeException('A listener after the stock refuses the change.');
        }, -100);

        $paid = (int) OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PAID)?->getId();
        $response = $this->jsonRequest('PATCH', '/api/admin/orders/'.$order->getId(), [
            'orderStatus' => '/api/admin/order_statutes/'.$paid,
            'orderProducts' => ['/api/admin/order_products/'.$line->getId()],
        ], $token, 'merge-patch+json');

        self::assertGreaterThanOrEqual(400, $response->getStatusCode(), 'The failure of the listener reaches the client.');
        $statement = Propel::getConnection()->prepare('SELECT `quantity` FROM `product_sale_elements` WHERE `id` = ?');
        $statement->execute([(int) $combination->getId()]);
        self::assertSame(4.0, (float) $statement->fetchColumn(), 'The stock stays taken with the status the core committed.');
        $statement = Propel::getConnection()->prepare('SELECT `status_id` FROM `order` WHERE `id` = ?');
        $statement->execute([(int) $order->getId()]);
        self::assertSame($paid, (int) $statement->fetchColumn(), 'The order stays paid.');
        self::assertEquals([new StockLevelChange((int) $combination->getId(), 5.0, 4.0)], $this->changes, 'The change of stock is told once.');
        self::assertSame([(int) $combination->getId()], $this->lowStock, 'The threshold crossed by the payment is signalled.');
    }

    private function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->getService(EventDispatcherInterface::class)->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function deleteTheCommittedFixtures(\Propel\Runtime\Connection\ConnectionInterface $connection): void
    {
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
    }
}
