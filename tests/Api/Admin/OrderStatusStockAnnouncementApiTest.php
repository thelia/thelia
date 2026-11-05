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

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\ProductSaleElement\LowStockReachedEvent;
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\ProductSaleElement\StockLevelChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Order\Enum\OrderStatusActionTrigger;
use Thelia\Domain\Order\StatusAction\Effect\AdjustStockAction;
use Thelia\Domain\Order\StatusAction\OrderStatusActionRunner;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusAction;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Support\Order\RecordsStatements;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * A payment written through `PATCH /api/admin/orders/{id}` takes the stock inside the transaction
 * the admin API opens around the status event: what it did to the stock is told once that
 * transaction is committed (the rollback: OrderStatusRolledBackStockAnnouncementApiTest).
 */
final class OrderStatusStockAnnouncementApiTest extends ApiTestCase
{
    use RecordsStatements;

    /** @var list<StockLevelChange> */
    private array $changes = [];

    /** @var list<int> the sale elements whose low stock was signalled */
    private array $lowStock = [];

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

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
        // The stock of the order is left for its payment: the payment takes it.
        $this->listen(
            TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque'),
            static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(false),
            512,
        );

        // The transaction this test wraps itself in is not one of a caller.
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn($this->getPropelConnection());
    }

    protected function tearDown(): void
    {
        $dispatcher = $this->getService(EventDispatcherInterface::class);

        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $dispatcher->removeListener($eventName, $listener);
        }

        $this->getService(TestCallerTransaction::class)->reset();
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testAPaymentTellsWhatItTookOnceTheAdminApiCommitted(): void
    {
        [$order, $combination] = $this->unpaidOrderOf(5, lowStockThreshold: 4);

        $response = $this->payThrough($order);

        self::assertJsonResponseSuccessful($response);
        self::assertSame(4.0, $this->stockOf($combination));
        self::assertEquals([new StockLevelChange((int) $combination->getId(), 5.0, 4.0)], $this->changes);
        self::assertSame([(int) $combination->getId()], $this->lowStock, 'The threshold crossed by the payment is signalled.');
    }

    /**
     * When the shop reserves its stock, the stock rows a payment takes from are locked right after the
     * order, before the persistence reads anything: the read view of the transaction then sees the
     * reservations of the other carts as they stand.
     */
    public function testAPaymentLocksTheStockRowsBeforeAnyPlainReadOfItsTransaction(): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::resetCache();
        [$order, $combination] = $this->unpaidOrderOf(5);
        $token = $this->authenticateAsAdmin();

        $statements = $this->statementsDuring(fn () => self::assertJsonResponseSuccessful($this->payThrough($order, $token)));

        $orderLock = array_key_first(array_filter($statements, static fn (string $statement): bool => str_starts_with($statement, 'SELECT `status_id` FROM `order`') && str_contains($statement, 'FOR UPDATE')));
        self::assertIsInt($orderLock, 'The order is locked first.');
        self::assertStringContainsString(
            'FROM `product_sale_elements` WHERE `id` IN ('.$combination->getId().') ORDER BY `id` FOR UPDATE',
            $statements[$orderLock + 1] ?? '',
            'The stock row the payment takes from is the very next statement.',
        );
    }

    /**
     * What the listeners answer for a payment is asked once for the whole change (the plan the admin
     * API works out before its transaction), not again by the status event for its locks and for its
     * stock.
     */
    public function testAPaymentAsksTheStockOperationOnce(): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::resetCache();
        [$order, $combination] = $this->unpaidOrderOf(5);
        $token = $this->authenticateAsAdmin();

        $asked = 0;
        $this->listen(TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE, static function () use (&$asked): void {
            ++$asked;
        }, 1024);

        self::assertJsonResponseSuccessful($this->payThrough($order, $token));

        self::assertSame(4.0, $this->stockOf($combination), 'The payment took its unit.');
        self::assertSame(1, $asked, 'The operation of the stock is asked once per payment.');
    }

    /**
     * The same for a status change that is no payment of the stock: the transaction asks once, and
     * the plan asks nothing.
     */
    public function testAChangeOfStatusThatIsNoPaymentAsksTheStockOperationOnce(): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::resetCache();
        [$order] = $this->unpaidOrderOf(5);
        $token = $this->authenticateAsAdmin();

        $asked = 0;
        $this->listen(TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE, static function () use (&$asked): void {
            ++$asked;
        }, 1024);

        $cancelled = (int) OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_CANCELED, $this->getPropelConnection())?->getId();
        self::assertJsonResponseSuccessful($this->jsonRequest('PATCH', '/api/admin/orders/'.$order->getId(), [
            'orderStatus' => '/api/admin/order_statutes/'.$cancelled,
        ], $token, 'merge-patch+json'));

        self::assertSame(1, $asked);
    }

    /**
     * An action `adjust_stock` (decrease) of the transition takes the stock the way a payment does:
     * its rows are locked right after the order, before the persistence reads anything.
     */
    public function testAnActionThatTakesTheStockLocksItsRowsBeforeAnyPlainRead(): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::resetCache();
        [$order, $combination] = $this->unpaidOrderOf(5);
        $processing = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PROCESSING, $this->getPropelConnection());
        $sent = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_SENT, $this->getPropelConnection());
        $order->setStatusId($processing->getId())->save($this->getPropelConnection());
        (new OrderStatusAction())
            ->setTriggerType(OrderStatusActionTrigger::TRANSITION->value)
            ->setFromStatusId($processing->getId())
            ->setToStatusId($sent->getId())
            ->setActionType(AdjustStockAction::getType())
            ->setDecodedPayload([AdjustStockAction::FIELD_OPERATION => AdjustStockAction::OPERATION_DECREASE])
            ->setPosition(1)
            ->setActive(true)
            ->save($this->getPropelConnection());
        $this->getService(OrderStatusActionRunner::class)->reset();
        $token = $this->authenticateAsAdmin();

        $statements = $this->statementsDuring(fn () => self::assertJsonResponseSuccessful($this->jsonRequest('PATCH', '/api/admin/orders/'.$order->getId(), [
            'orderStatus' => '/api/admin/order_statutes/'.$sent->getId(),
        ], $token, 'merge-patch+json')));

        $orderLock = array_key_first(array_filter($statements, static fn (string $statement): bool => str_starts_with($statement, 'SELECT `status_id` FROM `order`') && str_contains($statement, 'FOR UPDATE')));
        self::assertIsInt($orderLock, 'The order is locked first.');
        self::assertStringContainsString(
            'FROM `product_sale_elements` WHERE `id` IN ('.$combination->getId().') ORDER BY `id` FOR UPDATE',
            $statements[$orderLock + 1] ?? '',
            'The stock row the action takes from is the very next statement.',
        );
        self::assertSame(4.0, $this->stockOf($combination), 'The action took its unit.');
        $this->getService(OrderStatusActionRunner::class)->reset();
    }

    /**
     * @return array{0: Order, 1: ProductSaleElements}
     */
    private function unpaidOrderOf(int $quantity, ?float $lowStockThreshold = null): array
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $combination = $factory->productSaleElement($product, ['quantity' => $quantity]);
        $combination->setLowStockThreshold($lowStockThreshold)->save($this->getPropelConnection());

        $order = $factory->order();
        $line = $factory->orderProduct($order, [
            'productSaleElementsId' => $combination->getId(),
            'productSaleElementsRef' => $combination->getRef(),
            'productRef' => $product->getRef(),
            'quantity' => 1.0,
        ]);
        (new OrderProductTax())->setOrderProductId($line->getId())->setTitle('VAT')->setAmount('2')->save($this->getPropelConnection());

        return [$order, $combination];
    }

    private function payThrough(Order $order, ?string $token = null): \Symfony\Component\HttpFoundation\Response
    {
        $paid = (int) OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PAID, $this->getPropelConnection())?->getId();

        $lines = [];

        foreach ($order->getOrderProducts() as $line) {
            $lines[] = '/api/admin/order_products/'.$line->getId();
        }

        return $this->jsonRequest('PATCH', '/api/admin/orders/'.$order->getId(), [
            'orderStatus' => '/api/admin/order_statutes/'.$paid,
            'orderProducts' => $lines,
        ], $token ?? $this->authenticateAsAdmin(), 'merge-patch+json');
    }

    private function stockOf(ProductSaleElements $combination): float
    {
        $statement = $this->getPropelConnection()->prepare('SELECT `quantity` FROM `product_sale_elements` WHERE `id` = ?');
        $statement->execute([(int) $combination->getId()]);

        return (float) $statement->fetchColumn();
    }

    private function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->getService(EventDispatcherInterface::class)->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }
}
