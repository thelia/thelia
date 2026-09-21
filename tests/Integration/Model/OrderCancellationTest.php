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

namespace Thelia\Tests\Integration\Model;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * Cancelling an order is a status change like any other, and it goes down the same
 * road: ORDER_UPDATE_STATUS, so that the stock taken at the placement is given back and
 * the listeners a shop registered on its statuses run. Writing the status straight to the
 * row used to skip all of it.
 */
final class OrderCancellationTest extends IntegrationTestCase
{
    private const ORDERED_QUANTITY = 3.0;

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

    /**
     * AC6 — setCancelled() raises ORDER_UPDATE_STATUS: the stock comes back and the
     * status listeners run.
     */
    public function testAC6CancellingAnOrderGivesItsStockBackAndRunsTheStatusListeners(): void
    {
        [$order, $productSaleElements] = $this->unpaidOrderThatTookItsStock();
        $stockBeforeCancelling = $this->stockOf($productSaleElements);

        $statusChanges = [];
        $this->listen(TheliaEvents::ORDER_UPDATE_STATUS, static function (OrderEvent $event) use (&$statusChanges): void {
            $statusChanges[] = [$event->getOrder()->getId(), $event->getStatus()];
        }, priority: -1024);

        $order->setCancelled($this->dispatcher());

        $reread = OrderQuery::create()->findPk($order->getId(), $this->getPropelConnection());
        self::assertNotNull($reread);
        self::assertTrue($reread->isCancelled(), 'The order ends up cancelled.');
        self::assertSame(
            $stockBeforeCancelling + self::ORDERED_QUANTITY,
            $this->stockOf($productSaleElements),
            'The stock the order took at its placement is given back.',
        );
        self::assertCount(1, $statusChanges, 'A listener on ORDER_UPDATE_STATUS saw the change.');
        self::assertSame($order->getId(), $statusChanges[0][0]);
        self::assertSame($reread->getStatusId(), $statusChanges[0][1]);
    }

    /**
     * An order placed by a module that takes the stock on creation, with the stock
     * already taken: what a cancellation has to give back.
     *
     * @return array{0: Order, 1: ProductSaleElements}
     */
    private function unpaidOrderThatTookItsStock(): array
    {
        $this->listen(
            TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque'),
            static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(true),
        );

        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => 10],
        );
        $productSaleElements = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->findOne($this->getPropelConnection());
        self::assertInstanceOf(ProductSaleElements::class, $productSaleElements);

        $order = $this->factory->order($this->factory->customer($this->factory->customerTitle()));

        (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef($product->getRef())
            ->setProductSaleElementsRef($productSaleElements->getRef())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setTitle('Cancelled line')
            ->setQuantity(self::ORDERED_QUANTITY)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save($this->getPropelConnection());

        $productSaleElements
            ->setQuantity($productSaleElements->getQuantity() - self::ORDERED_QUANTITY)
            ->save($this->getPropelConnection());

        return [$order, $productSaleElements];
    }

    private function stockOf(ProductSaleElements $productSaleElements): float
    {
        return (float) ProductSaleElementsQuery::create()
            ->findPk($productSaleElements->getId(), $this->getPropelConnection())
            ?->getQuantity();
    }

    private function listen(string $eventName, callable $listener, int $priority = 512): void
    {
        $this->dispatcher()->addListener($eventName, $listener, $priority);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return static::getContainer()->get('event_dispatcher');
    }
}
