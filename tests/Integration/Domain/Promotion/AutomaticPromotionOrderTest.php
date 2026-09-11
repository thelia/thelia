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

namespace Thelia\Tests\Integration\Domain\Promotion;

use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Operators;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Domain\Promotion\Coupon\Type\BuyXGetY;
use Thelia\Model\Cart;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Model\Customer;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderCoupon;
use Thelia\Model\OrderCouponQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * What an automatic promotion leaves on a placed order: the promotion is copied
 * onto the order the way a typed coupon is, and the gift it put in the cart is
 * ordered and taken out of stock like any other line.
 */
final class AutomaticPromotionOrderTest extends ActionIntegrationPromotionTestCase
{
    public function testAnAutomaticPromotionIsRecordedOnTheOrderWithoutACode(): void
    {
        [$coupon, , $gift, $order] = $this->placeOrderWithAnOfferedGift();

        $orderCoupon = $this->orderCouponOf($order);

        self::assertSame($coupon->getId(), $orderCoupon->getCouponId(), 'The promotion is linked by id, the only reliable link when there is no code.');
        self::assertNull($orderCoupon->getCode());
        self::assertSame($coupon->getSerializedEffects(), $orderCoupon->getSerializedEffects());
        self::assertSame($coupon->getType(), $orderCoupon->getType());
        self::assertGreaterThan(0.0, (float) $orderCoupon->getAmount());
        self::assertNotNull($gift->getId());
    }

    public function testTheOrderKeepsTheDiscountTheCartWasPricedWith(): void
    {
        [, , , $order, $cartDiscount] = $this->placeOrderWithAnOfferedGift();

        self::assertGreaterThan(0.0, $cartDiscount);
        self::assertSame($cartDiscount, (float) $order->getDiscount());
    }

    public function testTheOfferedProductIsTakenOutOfStockLikeAnyOtherLine(): void
    {
        // The Cheque module keeps the stock until the payment is confirmed; the
        // point here is that the gift line follows whatever rule the order does,
        // so make the order manage stock on creation and watch both lines move.
        $this->dispatcher->addListener(
            TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque'),
            static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(true),
        );

        [, $trigger, $gift] = $this->placeOrderWithAnOfferedGift();

        self::assertSame(98.0, $this->stockOf($trigger), 'The two triggering units must leave the stock.');
        self::assertSame(99.0, $this->stockOf($gift), 'The offered unit must leave the stock too.');
    }

    public function testTheUsageOfAnAutomaticPromotionIsCountedWhenTheOrderIsPaidAndGivenBackOnCancellation(): void
    {
        [$coupon, , , $order] = $this->placeOrderWithAnOfferedGift(maxUsage: 1);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);
        self::assertSame(0, CouponQuery::create()->findPk($coupon->getId())?->getMaxUsage());

        $this->moveOrderTo($order, OrderStatus::CODE_CANCELED);
        self::assertSame(1, CouponQuery::create()->findPk($coupon->getId())?->getMaxUsage());
    }

    /**
     * A coupon the customer typed in keeps its code on the order: the id is an
     * addition, not a replacement.
     */
    public function testATypedCouponIsStillRecordedWithItsCode(): void
    {
        $customer = $this->signedInCustomer();
        $coupon = $this->factory->coupon([
            'code' => 'TYPED-'.uniqid(),
            'conditions' => $this->atLeastLines(1),
        ]);

        $trigger = $this->product();
        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger);

        $this->session()->setConsumedCoupons([$coupon->getCode()]);
        $this->recomputeDiscount();

        $order = $this->placeOrder($customer, $cart);

        $orderCoupon = $this->orderCouponOf($order);

        self::assertSame($coupon->getId(), $orderCoupon->getCouponId());
        self::assertSame($coupon->getCode(), $orderCoupon->getCode());
    }

    /**
     * @return array{0: Coupon, 1: Product, 2: Product, 3: Order, 4: float}
     */
    public function testAPromotionWithdrawnBeforeTheOrderIsNotBilledOnIt(): void
    {
        $customer = $this->signedInCustomer();
        $coupon = $this->automaticPromotion(conditions: $this->atLeastLines(1));

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $this->product());
        // Action\Order::create() copies the order addresses off the cart, the way the
        // checkout does once the buyer has chosen them.
        $cartAddress = $this->factory->cartAddress($this->factory->address($customer));
        $cart
            ->setAddressDeliveryId($cartAddress->getId())
            ->setAddressInvoiceId($cartAddress->getId())
            ->save();
        $cart->reload();

        self::assertSame(5.0, (float) $cart->getDiscount(), 'The promotion applies while the buyer fills the cart.');

        // The merchant ends the promotion while the buyer sits on the payment page.
        // Nothing touches the cart after that, so nothing recomputes its discount.
        $coupon->setIsEnabled(false)->save();

        $order = $this->payOrder($customer, $cart);

        self::assertSame(
            0.0,
            (float) $order->getDiscount(),
            'A promotion that no longer applies must not be billed on the order.',
        );
        self::assertCount(
            0,
            OrderCouponQuery::create()->filterByOrderId($order->getId())->find(),
            'And the order must not carry a discount no coupon row explains.',
        );
    }

    /**
     * The checkout path a buyer takes: ORDER_PAY, which is what gives the
     * promotions a last chance to be priced before the order is built.
     */
    private function payOrder(Customer $customer, Cart $cart): Order
    {
        $sessionOrder = $this->session()->getOrder();
        $sessionOrder
            ->setDeliveryOrderAddressId($this->factory->orderAddress()->getId())
            ->setInvoiceOrderAddressId($this->factory->orderAddress()->getId())
            ->setStatusId(OrderStatusQuery::getNotPaidStatus()?->getId())
            ->setDeliveryModuleId(ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId())
            ->setPaymentModuleId(ModuleQuery::create()->findOneByCode('Cheque')?->getId())
            ->setCustomerId($customer->getId())
            ->setCartId($cart->getId())
            ->setPostage('0')
            ->setPostageTax('0');

        $event = new OrderEvent($sessionOrder);
        $this->dispatch($event, TheliaEvents::ORDER_PAY);

        return $event->getPlacedOrder();
    }

    private function placeOrderWithAnOfferedGift(int $maxUsage = Coupon::UNLIMITED_COUPON_USE): array
    {
        $customer = $this->signedInCustomer();

        $trigger = $this->product();
        $gift = $this->product();

        $coupon = $this->automaticPromotion(
            type: 'thelia.coupon.type.buy_x_get_y',
            effects: [
                BuyXGetY::TRIGGER_SCOPE_FIELD => BuyXGetY::TRIGGER_SCOPE_PRODUCT,
                BuyXGetY::TRIGGER_IDS_FIELD => [$trigger->getId()],
                BuyXGetY::TRIGGER_QUANTITY_FIELD => 2,
                BuyXGetY::TARGET_MODE_FIELD => BuyXGetY::TARGET_MODE_PRODUCT,
                BuyXGetY::TARGET_PRODUCT_ID_FIELD => $gift->getId(),
                BuyXGetY::OFFERED_QUANTITY_FIELD => 1,
                BuyXGetY::DISCOUNT_TYPE_FIELD => BuyXGetY::DISCOUNT_TYPE_FREE,
                BuyXGetY::DISCOUNT_VALUE_FIELD => 0.0,
            ],
            conditions: $this->atLeastLines(1),
            overrides: ['maxUsage' => $maxUsage],
        );

        $cart = $this->newEmptyCart();
        $this->addItem($cart, $trigger, quantity: 2);

        $cart->reload();
        $cartDiscount = (float) $cart->getDiscount();

        $order = $this->placeOrder($customer, $cart);

        return [$coupon, $trigger, $gift, $order, $cartDiscount];
    }

    private function stockOf(Product $product): float
    {
        return (float) ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne()
            ?->getQuantity();
    }

    private function placeOrder(Customer $customer, Cart $cart): Order
    {
        $sessionOrder = $this->session()->getOrder();
        $sessionOrder
            ->setDeliveryOrderAddressId($this->factory->orderAddress()->getId())
            ->setInvoiceOrderAddressId($this->factory->orderAddress()->getId())
            ->setStatusId(OrderStatusQuery::getNotPaidStatus()?->getId())
            ->setDeliveryModuleId(ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId())
            ->setPaymentModuleId(ModuleQuery::create()->findOneByCode('Cheque')?->getId())
            ->setPostage('0')
            ->setPostageTax('0');

        $order = $this->getService(OrderFacade::class)->createOrder(
            $this->dispatcher,
            $sessionOrder,
            $this->session()->getCurrency(),
            $this->factory->lang(),
            $cart,
            $customer,
            useOrderDefinedAddresses: true,
        );

        // What the checkout does once the order exists: freeze the coupons on it.
        $this->dispatch(new OrderEvent($order), TheliaEvents::ORDER_BEFORE_PAYMENT);

        return $order;
    }

    private function orderCouponOf(Order $order): OrderCoupon
    {
        $orderCoupons = OrderCouponQuery::create()->filterByOrderId($order->getId())->find();

        self::assertCount(1, $orderCoupons);

        return $orderCoupons->getFirst();
    }

    private function moveOrderTo(Order $order, string $statusCode): void
    {
        $status = OrderStatusQuery::create()->findOneByCode($statusCode);
        self::assertNotNull($status, "Seeded order status '$statusCode' is missing.");

        $event = new OrderEvent($order);
        $event->setStatus($status->getId());

        $this->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    private function signedInCustomer(): Customer
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->getService(SecurityContext::class)->setCustomerUser($customer);

        return $customer;
    }

    private function atLeastLines(int $lines): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(ConditionFactory::class)->build(
            'thelia.condition.match_for_x_articles',
            ['quantity' => Operators::SUPERIOR_OR_EQUAL],
            ['quantity' => $lines],
        );

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }
}
