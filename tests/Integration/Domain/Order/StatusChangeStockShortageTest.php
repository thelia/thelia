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

use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\SaleUnit;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\MovesOrders;

/**
 * A status change that takes a stock the shop does not have is refused, and the refusal names
 * the sale element that is short: its reference, then " : Not enough stock". The back office
 * and the stock actions show that message as it is.
 */
final class StatusChangeStockShortageTest extends ActionIntegrationTestCase
{
    use MovesOrders;

    private const ORDERED = 3.0;

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('check_available_stock', '1');
        $this->stockIsLeftForThePayment();
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        $this->getService(SaleUnitActivityChecker::class)->reset();
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testASharedStockTooLowRefusesThePaymentNamingTheSaleElement(): void
    {
        $short = $this->createProductSaleElements(1.0);
        $order = $this->orderOf($short, null);

        $this->assertThePaymentIsRefused($order, $short->getRef().' : Not enough stock');

        self::assertSame(1.0, (float) ProductSaleElementsQuery::create()->findPk($short->getId())?->getQuantity());
    }

    public function testASaleUnitStockTooLowRefusesThePaymentNamingTheSaleElement(): void
    {
        $short = $this->createProductSaleElements(50.0);
        $saleUnit = $this->factory->saleUnit();
        $this->factory->saleUnitProductSaleElements($saleUnit, $short, 1.0);
        $order = $this->orderOf($short, $saleUnit);

        $this->assertThePaymentIsRefused($order, $short->getRef().' : Not enough stock');
    }

    private function assertThePaymentIsRefused(Order $order, string $expectedMessage): void
    {
        try {
            $this->moveOrderTo($order, OrderStatus::CODE_PAID);
            self::fail('A stock the order cannot take must refuse the status change.');
        } catch (TheliaProcessException $refusal) {
            self::assertSame($expectedMessage, $refusal->getMessage());
        }

        self::assertSame(
            $this->orderStatus(OrderStatus::CODE_NOT_PAID)->getId(),
            OrderQuery::create()->findPk($order->getId())?->getStatusId(),
            'A refused status change leaves the order in its status.',
        );
    }

    /**
     * The payment module of the fixtures leaves the stock for the payment, so that paying is
     * what takes it.
     */
    private function stockIsLeftForThePayment(): void
    {
        $listener = static function (ManageStockOnCreationEvent $event): void {
            $event->setManageStock(false);
        };
        $eventName = TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque');
        $this->dispatcher->addListener($eventName, $listener, -1024);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function orderOf(ProductSaleElements $productSaleElements, ?SaleUnit $saleUnit): Order
    {
        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_NOT_PAID]);
        $order->setSaleUnitId($saleUnit?->getId())->save($this->getPropelConnection());

        (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef('REF-'.$productSaleElements->getId())
            ->setProductSaleElementsRef((string) $productSaleElements->getRef())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setTitle('A product short of stock')
            ->setQuantity(self::ORDERED)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->setVirtual(0)
            ->save($this->getPropelConnection());

        $order->clearOrderProducts();

        return $order;
    }

    private function createProductSaleElements(float $quantity): ProductSaleElements
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());

        return $this->factory->productSaleElement($product, ['quantity' => $quantity]);
    }
}
