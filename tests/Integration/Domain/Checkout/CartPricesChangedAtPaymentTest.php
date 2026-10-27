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

namespace Thelia\Tests\Integration\Domain\Checkout;

use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Operators;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Cart\Exception\CartPricesChangedException;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Tests\Integration\Domain\Promotion\ActionIntegrationPromotionTestCase;

/**
 * A group that moves the price of a line between the cart and the payment must not leave the
 * coupon, the postage, the fingerprint and the parts of the order on the old total: the
 * payment prices the lines again first, stops, and the buyer confirms prices they have seen.
 */
final class CartPricesChangedAtPaymentTest extends ActionIntegrationPromotionTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = $this->factory->customer($this->factory->customerTitle());
        $this->getService(SecurityContext::class)->setCustomerUser($this->customer);

        // The carrier quotes again when the lines move, as it does at every step of the checkout: it
        // quotes nothing here, and the postage of the cart stays at zero.
        $this->dispatcher->addListener(
            TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
            static function (DeliveryPostageEvent $event): void {
                $event->setValidModule(true);
                $event->setPostage(new OrderPostage(0.0, 0.0, 'VAT'));
                $event->stopPropagation();
            },
            512,
        );

        // The payment module would answer with a page of its own: only the placement matters.
        $this->dispatcher->addListener(
            TheliaEvents::MODULE_PAY,
            static fn (OrderPaymentEvent $event) => $event->stopPropagation(),
            512,
        );
    }

    public function testAGroupChangedBetweenTheCartAndThePaymentIsRefusedThenPaidOnTheNewPricesWithTheCoupon(): void
    {
        $this->automaticPromotion('thelia.coupon.type.remove_x_percent', ['percentage' => 10.0], $this->atLeastOneLine());
        $cart = $this->cartWithOneLine(basePrice: 100.0);
        $discountOnTheOldPrices = (float) $cart->getDiscount();
        self::assertGreaterThan(0.0, $discountOnTheOldPrices, 'the coupon applies to the cart as the buyer filled it');

        $pro = $this->moveTheCustomerTo($this->factory->customerGroup('pro-at-payment', 15.0));

        try {
            $this->pay($cart);
            self::fail('The payment went through on lines priced for the group the buyer left.');
        } catch (CartPricesChangedException $refusal) {
            self::assertSame([$this->lineOf($cart)->getId()], $refusal->cartItemIds);
            self::assertStringContainsString('prices of your cart have changed', $refusal->getMessage());
        }

        self::assertSame([], $this->ordersOf($cart), 'nothing is ordered on the old total');
        self::assertSame(85.0, round((float) $this->lineOf($cart)->getPrice(), 6), 'the cart now reads the price of the new group');

        // The refusal itself priced the coupon again on the new lines: the buyer is back on their
        // cart, in a new request, and reads that discount (nothing recomputes it by hand).
        CartTableMap::clearInstancePool();
        CartItemTableMap::clearInstancePool();
        $cart->reload();
        $discountOnTheNewPrices = (float) $cart->getDiscount();
        self::assertEqualsWithDelta($discountOnTheOldPrices * 0.85, $discountOnTheNewPrices, 0.02, '10 % of the price they will pay');

        $order = $this->pay($cart);

        self::assertCount(1, $this->ordersOf($cart));
        self::assertSame($pro->getId(), $order->getCustomerGroupId());
        self::assertEqualsWithDelta($discountOnTheNewPrices, (float) $order->getDiscount(), 0.001, 'the order carries the coupon calculated on the final prices');
        self::assertSame([85.0], array_map(
            static fn ($line): float => round((float) $line->getPrice(), 6),
            iterator_to_array(OrderProductQuery::create()->filterByOrderId($order->getId())->find(), false),
        ));
    }

    public function testACartNoSessionHoldsHasItsPromotionDiscountPricedOnTheNewLines(): void
    {
        $this->automaticPromotion('thelia.coupon.type.remove_x_percent', ['percentage' => 10.0], $this->atLeastOneLine());
        $cart = $this->cartWithOneLine(basePrice: 100.0, heldBySession: false);
        self::assertEqualsWithDelta(10.0, (float) $cart->getDiscount(), 0.02);

        $this->moveTheCustomerTo($this->factory->customerGroup('pro-api', 15.0));

        try {
            $this->pay($cart);
            self::fail('The payment went through on lines priced for the group the buyer left.');
        } catch (CartPricesChangedException) {
        }

        self::assertEqualsWithDelta(8.5, (float) CartQuery::create()->findPk($cart->getId())?->getDiscount(), 0.02, 'the cart no session holds is priced from itself');
    }

    public function testADiscountThePromotionsDoNotExplainIsLeftAsItIsWhenNoSessionCanSayWhy(): void
    {
        $this->automaticPromotion('thelia.coupon.type.remove_x_percent', ['percentage' => 10.0], $this->atLeastOneLine());
        $cart = $this->cartWithOneLine(basePrice: 100.0, heldBySession: false);
        // What a browser session leaves on its cart when the buyer typed a code in: more than the promotions give.
        $cart->setDiscount('25.000000')->save();

        $this->moveTheCustomerTo($this->factory->customerGroup('pro-code', 15.0));

        try {
            $this->pay($cart);
            self::fail('The payment went through on lines priced for the group the buyer left.');
        } catch (CartPricesChangedException) {
        }

        self::assertEqualsWithDelta(25.0, (float) CartQuery::create()->findPk($cart->getId())?->getDiscount(), 0.001, 'the code typed in is not dropped by a pricing that cannot see it');
    }

    public function testACartWhosePricesDidNotMoveIsPaidAtOnceAndNothingIsWritten(): void
    {
        $this->factory->customerGroup('pro-unused', 15.0);
        $cart = $this->cartWithOneLine(basePrice: 100.0);
        $line = $this->lineOf($cart);
        $updatedAt = $line->getUpdatedAt('U.u');

        $order = $this->pay($cart);

        self::assertNotNull($order->getId());
        self::assertSame($updatedAt, $this->lineOf($cart)->getUpdatedAt('U.u'), 'a line that did not move is not written');
    }

    public function testAnOfferedLineIsNotAPriceThatMoved(): void
    {
        $this->factory->customerGroup('pro-offered', 15.0);
        $cart = $this->cartWithOneLine(basePrice: 100.0);
        $this->factory->cartItem($cart, $this->product(['basePrice' => 7.0]), overrides: ['price' => '0.000000', 'promoPrice' => '0.000000', 'isOffered' => 1]);

        $order = $this->pay($cart);

        self::assertNotNull($order->getId());
    }

    public function testAShopWhereNoGroupPricesAnythingKeepsTheLinesAsWritten(): void
    {
        $cart = $this->cartWithOneLine(basePrice: 100.0);
        CartItemQuery::create()->filterByCartId($cart->getId())->update(['Price' => '12.500000', 'PromoPrice' => '12.500000']);
        CartItemTableMap::clearInstancePool();
        CartTableMap::clearInstancePool();

        $order = $this->pay($cart);

        self::assertNotNull($order->getId(), 'no group changes a price: the shop keeps the line it wrote, as before');
    }

    /**
     * @param bool $heldBySession true for the cart of a browser (the session knows it), false for one an API client
     *                            carries by its token
     */
    private function cartWithOneLine(float $basePrice, bool $heldBySession = true): Cart
    {
        $cart = $this->newEmptyCart();
        $cart->setCustomerId($this->customer->getId())->save();

        // The events that fill the cart price its discount on the cart the session holds.
        $this->session()->set(Session::SESSION_CART_ID_NAME, $cart->getId());

        $this->addItem($cart, $this->product(['basePrice' => $basePrice]));

        $cartAddress = $this->factory->cartAddress($this->factory->address($this->customer));
        $cart
            ->setAddressDeliveryId($cartAddress->getId())
            ->setAddressInvoiceId($cartAddress->getId())
            ->setDeliveryModuleId(ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId())
            ->setPaymentModuleId(ModuleQuery::create()->findOneByCode('Cheque')?->getId())
            ->setPostage('0')
            ->setPostageTax('0')
            ->save();
        $cart->reload();
        // The discount is priced once the delivery country, which sets the taxes, is known.
        $this->recomputeDiscount();
        $cart->reload();

        if (!$heldBySession) {
            $this->session()->remove(Session::SESSION_CART_ID_NAME);
        }

        return $cart;
    }

    /**
     * What the back office does while the buyer sits on their cart: the account goes to
     * another group, and the next request reads it.
     */
    private function moveTheCustomerTo(CustomerGroup $group): CustomerGroup
    {
        $this->factory->assignCustomerGroup($this->customer, $group);
        CustomerTableMap::clearInstancePool();
        CartTableMap::clearInstancePool();
        $this->getService('services_resetter')->reset();

        return $group;
    }

    private function pay(Cart $cart): Order
    {
        $cart = CartQuery::create()->findPk($cart->getId());

        return $this->getService(CheckoutPaymentService::class)->payAndReturnOutcome(new OrderPaymentRequest(
            $cart,
            (int) $cart->getAddressDeliveryId(),
            (int) $cart->getAddressInvoiceId(),
            (int) $cart->getDeliveryModuleId(),
            (int) $cart->getPaymentModuleId(),
        ))->placedOrder;
    }

    private function lineOf(Cart $cart): CartItem
    {
        CartItemTableMap::clearInstancePool();

        return CartItemQuery::create()->filterByCartId($cart->getId())->filterByIsOffered(0)->findOne();
    }

    /**
     * @return list<Order>
     */
    private function ordersOf(Cart $cart): array
    {
        return OrderQuery::create()->filterByCartId($cart->getId())->orderById()->find()->getData();
    }

    private function atLeastOneLine(): string
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(ConditionFactory::class)->build(
            'thelia.condition.match_for_x_articles',
            ['quantity' => Operators::SUPERIOR_OR_EQUAL],
            ['quantity' => 1],
        );

        return $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions);
    }
}
