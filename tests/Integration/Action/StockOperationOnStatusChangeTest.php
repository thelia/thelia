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

namespace Thelia\Tests\Integration\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\GetStockUpdateOperationOnOrderStatusChangeEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The stock operation of a status change is decided by the core listener at priority 128,
 * and a module listening after it has the last word: that is how a shop replaces the core
 * rule without touching the core.
 */
final class StockOperationOnStatusChangeTest extends IntegrationTestCase
{
    private const ORDERED_QUANTITY = 3.0;
    private const STOCK = 10;
    private const AFTER_THE_CORE = 64;

    private FixtureFactory $factory;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        parent::tearDown();
    }

    public function testPayingAnOrderTakesItsStockWhenNoListenerDecidesOtherwise(): void
    {
        [$order, $productSaleElements] = $this->orderWithOneLine('not_paid');

        $this->changeStatus($order, 'paid');

        self::assertSame((float) self::STOCK - self::ORDERED_QUANTITY, $this->stockOf($productSaleElements));
    }

    public function testAListenerAfterTheCoreSeesItsDecisionAndCanCancelIt(): void
    {
        [$order, $productSaleElements] = $this->orderWithOneLine('not_paid');
        $operationSeen = null;
        $this->listen(
            TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE,
            static function (GetStockUpdateOperationOnOrderStatusChangeEvent $event) use (&$operationSeen): void {
                $operationSeen = $event->getOperation();
                $event->setOperation($event::DO_NOTHING);
            },
        );

        $this->changeStatus($order, 'paid');

        self::assertSame(GetStockUpdateOperationOnOrderStatusChangeEvent::DECREASE_STOCK, $operationSeen);
        self::assertSame((float) self::STOCK, $this->stockOf($productSaleElements));
        self::assertSame('paid', $this->statusCodeOf($order));
    }

    public function testAListenerAfterTheCoreCanMoveTheStockOnATransitionTheCoreIgnores(): void
    {
        [$order, $productSaleElements] = $this->orderWithOneLine('paid');
        $this->listen(
            TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE,
            static function (GetStockUpdateOperationOnOrderStatusChangeEvent $event): void {
                if (GetStockUpdateOperationOnOrderStatusChangeEvent::DO_NOTHING === $event->getOperation()) {
                    $event->setOperation($event::INCREASE_STOCK);
                }
            },
        );

        $this->changeStatus($order, 'processing');

        self::assertSame((float) self::STOCK + self::ORDERED_QUANTITY, $this->stockOf($productSaleElements));
    }

    /**
     * @return array{0: Order, 1: ProductSaleElements}
     */
    private function orderWithOneLine(string $statusCode): array
    {
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => self::STOCK],
        );
        $productSaleElements = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->findOne($this->getPropelConnection());
        self::assertInstanceOf(ProductSaleElements::class, $productSaleElements);

        $order = $this->factory->order($this->factory->customer($this->factory->customerTitle()));
        $order->setStatusId($this->statusId($statusCode))->save($this->getPropelConnection());

        (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef($product->getRef())
            ->setProductSaleElementsRef($productSaleElements->getRef())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setTitle('Line')
            ->setQuantity(self::ORDERED_QUANTITY)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save($this->getPropelConnection());

        return [$order, $productSaleElements];
    }

    private function changeStatus(Order $order, string $statusCode): void
    {
        $event = new OrderEvent($order);
        $event->setStatus($this->statusId($statusCode));
        $this->dispatcher()->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    private function statusId(string $code): int
    {
        $status = OrderStatusQuery::create()->findOneByCode($code, $this->getPropelConnection());
        self::assertNotNull($status, \sprintf('The status "%s" is seeded.', $code));

        return $status->getId();
    }

    private function statusCodeOf(Order $order): ?string
    {
        return OrderQuery::create()->findPk($order->getId(), $this->getPropelConnection())?->getOrderStatus()?->getCode();
    }

    private function stockOf(ProductSaleElements $productSaleElements): float
    {
        return (float) ProductSaleElementsQuery::create()
            ->findPk($productSaleElements->getId(), $this->getPropelConnection())
            ?->getQuantity();
    }

    private function listen(string $eventName, callable $listener, int $priority = self::AFTER_THE_CORE): void
    {
        $this->dispatcher()->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}
