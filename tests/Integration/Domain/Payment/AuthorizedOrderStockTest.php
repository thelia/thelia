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

namespace Thelia\Tests\Integration\Domain\Payment;

use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatus;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\MovesOrders;

/**
 * An order on hold for capture is still an unpaid order: putting it on hold takes nothing
 * from the stock and gives nothing back, and what happens next — the capture, the
 * cancellation — moves the stock as it would have from "not paid".
 */
final class AuthorizedOrderStockTest extends ActionIntegrationTestCase
{
    use MovesOrders;

    private const ORDERED_QUANTITY = 3.0;

    private const STOCK = 10.0;

    private PaymentTransactionRecorder $recorder;

    /** @var callable */
    private $stockOnCreation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recorder = $this->getService(PaymentTransactionRecorder::class);
        $this->stockOnCreation = static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(true);
        $this->dispatcher->addListener(TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque'), $this->stockOnCreation, 512);
    }

    protected function tearDown(): void
    {
        $this->dispatcher->removeListener(TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque'), $this->stockOnCreation);

        parent::tearDown();
    }

    public function testAnAuthorizationThenItsCaptureTakeTheStockOnlyOnce(): void
    {
        [$order, $productSaleElements] = $this->orderThatTookItsStock();

        $authorization = $this->recorder->recordAuthorization($order, 30, 'AUTH-1', moduleCode: 'Cheque');

        self::assertSame(OrderStatus::CODE_AWAITING_CAPTURE, $this->reload($order)->getOrderStatus()->getCode());
        self::assertSame(self::STOCK - self::ORDERED_QUANTITY, $this->stockOf($productSaleElements), 'Putting the order on hold leaves the stock as the placement left it.');

        $this->recorder->recordCapture($order, 30, 'CAP-1', authorization: $authorization, moduleCode: 'Cheque');

        self::assertSame(OrderStatus::CODE_PAID, $this->reload($order)->getOrderStatus()->getCode());
        self::assertSame(self::STOCK - self::ORDERED_QUANTITY, $this->stockOf($productSaleElements), 'The capture does not take the stock a second time.');
    }

    public function testCancellingAnAuthorizedOrderGivesItsStockBack(): void
    {
        [$order, $productSaleElements] = $this->orderThatTookItsStock();
        $this->recorder->recordAuthorization($order, 30, 'AUTH-1', moduleCode: 'Cheque');

        $this->moveOrderTo($this->reload($order), OrderStatus::CODE_CANCELED);

        self::assertSame(OrderStatus::CODE_CANCELED, $this->reload($order)->getOrderStatus()->getCode());
        self::assertSame(self::STOCK, $this->stockOf($productSaleElements), 'The stock the placement took is given back.');
    }

    /**
     * @return array{0: Order, 1: ProductSaleElements}
     */
    private function orderThatTookItsStock(): array
    {
        $product = $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            $this->factory->currency(),
            ['baseQuantity' => (int) self::STOCK],
        );
        $productSaleElements = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->findOne();
        self::assertInstanceOf(ProductSaleElements::class, $productSaleElements);

        $order = $this->factory->order(null, ['postage' => 0]);

        (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef($product->getRef())
            ->setProductSaleElementsRef($productSaleElements->getRef())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setTitle('Authorized line')
            ->setQuantity(self::ORDERED_QUANTITY)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save();

        $productSaleElements->setQuantity(self::STOCK - self::ORDERED_QUANTITY)->save();

        return [$order, $productSaleElements];
    }

    private function stockOf(ProductSaleElements $productSaleElements): float
    {
        return (float) ProductSaleElementsQuery::create()->findPk($productSaleElements->getId())?->getQuantity();
    }

    private function reload(Order $order): Order
    {
        $order->reload();

        return $order;
    }
}
