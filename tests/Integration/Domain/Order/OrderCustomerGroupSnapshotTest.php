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

use Thelia\Domain\Cart\Exception\CartPricesChangedException;
use Thelia\Domain\Cart\Service\CartLinePricer;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Lang;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The group of the buyer and its discount rate are baked into the order the way the
 * customer discount rate is: stamped by the server from the customer record, and kept
 * whatever happens to the customer or to the group afterwards.
 */
final class OrderCustomerGroupSnapshotTest extends ActionIntegrationTestCase
{
    private OrderFacade $orderFacade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderFacade = $this->getService(OrderFacade::class);
    }

    public function testOrderKeepsTheGroupAndTheRateOfItsBuyer(): void
    {
        [$customer, $currency, $lang, $product] = $this->createCatalogFixtures();
        $group = $this->factory->customerGroup('pro-snapshot', 15.0);
        $this->factory->assignCustomerGroup($customer, $group);

        $order = $this->createOrderThroughFacade($customer, $currency, $lang, $product);

        self::assertSame($group->getId(), $order->getCustomerGroupId());
        self::assertSame(15.0, (float) $order->getCustomerGroupDiscountRate());

        OrderTableMap::clearInstancePool();
        $reloaded = OrderQuery::create()->findPk($order->getId());
        self::assertSame($group->getId(), $reloaded->getCustomerGroupId());
        self::assertSame(15.0, (float) $reloaded->getCustomerGroupDiscountRate());
        self::assertSame('pro-snapshot', $reloaded->getCustomerGroupCode());
    }

    public function testMovingTheCustomerOrChangingTheGroupRateLeavesThePlacedOrderAlone(): void
    {
        [$customer, $currency, $lang, $product] = $this->createCatalogFixtures();
        $group = $this->factory->customerGroup('moved-from', 15.0);
        $other = $this->factory->customerGroup('moved-to', 40.0);
        $this->factory->assignCustomerGroup($customer, $group);

        $order = $this->createOrderThroughFacade($customer, $currency, $lang, $product);

        $this->factory->assignCustomerGroup($customer, $other);
        $group->setDiscount('99')->save();

        OrderTableMap::clearInstancePool();
        $reloaded = OrderQuery::create()->findPk($order->getId());
        self::assertSame($group->getId(), $reloaded->getCustomerGroupId());
        self::assertSame(15.0, (float) $reloaded->getCustomerGroupDiscountRate());
    }

    public function testDeletingTheGroupKeepsTheRateAndEmptiesTheGroupWithoutError(): void
    {
        [$customer, $currency, $lang, $product] = $this->createCatalogFixtures();
        $group = $this->factory->customerGroup('to-delete', 20.0);
        $this->factory->assignCustomerGroup($customer, $group);

        $order = $this->createOrderThroughFacade($customer, $currency, $lang, $product);

        $group->delete();

        OrderTableMap::clearInstancePool();
        $reloaded = OrderQuery::create()->findPk($order->getId());
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getCustomerGroupId());
        self::assertNull($reloaded->getCustomerGroupCode());
        self::assertSame(20.0, (float) $reloaded->getCustomerGroupDiscountRate());
    }

    public function testUntaxedGroupGivesAnUntaxedOrderAndTheModeIsFrozen(): void
    {
        [$customer, $currency, $lang, $product] = $this->createCatalogFixtures();
        $group = $this->factory->customerGroup('untaxed-snapshot', 0.0, false);
        $this->factory->assignCustomerGroup($customer, $group);

        $order = $this->createOrderThroughFacade($customer, $currency, $lang, $product);

        self::assertSame(0, $order->getCustomerGroupDisplayTaxedPrice());

        $group->setDisplayTaxedPrice(1)->save();

        OrderTableMap::clearInstancePool();
        self::assertSame(0, OrderQuery::create()->findPk($order->getId())->getCustomerGroupDisplayTaxedPrice());
    }

    public function testDeletingTheGroupKeepsTheDisplayMode(): void
    {
        [$customer, $currency, $lang, $product] = $this->createCatalogFixtures();
        $group = $this->factory->customerGroup('untaxed-deleted', 0.0, false);
        $this->factory->assignCustomerGroup($customer, $group);

        $order = $this->createOrderThroughFacade($customer, $currency, $lang, $product);
        $group->delete();

        OrderTableMap::clearInstancePool();
        $reloaded = OrderQuery::create()->findPk($order->getId());
        self::assertNull($reloaded->getCustomerGroupId());
        self::assertSame(0, $reloaded->getCustomerGroupDisplayTaxedPrice());
    }

    public function testAGroupChangedBetweenTheCartAndTheOrderIsRefusedAndWritesNothing(): void
    {
        [$customer, $currency, $lang, $product] = $this->createCatalogFixtures(basePrice: 100.0);
        $this->factory->assignCustomerGroup($customer, $this->factory->customerGroup('filled-as', 15.0));
        $cart = $this->cartOf($customer, $currency, $product);
        // The session keeps the customer as they signed in, while the back office moves the
        // account to another group before the order is placed.
        $moved = $this->factory->customerGroup('ordered-as', 40.0);
        $sessionCopy = CustomerQuery::create()->findPk($customer->getId());
        CustomerQuery::create()->filterById($customer->getId())->update(['CustomerGroupId' => $moved->getId()]);
        $cart = $this->reloaded($cart);
        $orders = OrderQuery::create()->count();

        try {
            $this->placeOrder($cart, $sessionCopy, $currency, $lang);
            self::fail('an order was written on lines the group of its buyer no longer owes');
        } catch (CartPricesChangedException $refusal) {
            self::assertCount(1, $refusal->cartItemIds);
        }

        self::assertSame($orders, OrderQuery::create()->count(), 'no order is written');
        self::assertSame(85.0, round((float) $cart->getCartItems()->getFirst()->getPrice(), 6), 'the order factory writes no line: the checkout settles them before');
    }

    public function testLinesSettledOnTheNewGroupAreOrderedAndRecordedWithIt(): void
    {
        [$customer, $currency, $lang, $product] = $this->createCatalogFixtures(basePrice: 100.0);
        $this->factory->assignCustomerGroup($customer, $this->factory->customerGroup('filled-as', 15.0));
        $cart = $this->cartOf($customer, $currency, $product);
        $moved = $this->factory->customerGroup('ordered-as', 40.0);
        $sessionCopy = CustomerQuery::create()->findPk($customer->getId());
        CustomerQuery::create()->filterById($customer->getId())->update(['CustomerGroupId' => $moved->getId()]);
        $cart = $this->reloaded($cart);

        $this->getService(CartLinePricer::class)->reprice($cart, $currency, $cart->getCustomer());
        $order = $this->placeOrder($cart, $sessionCopy, $currency, $lang);

        self::assertSame($moved->getId(), $order->getCustomerGroupId());
        self::assertSame(40.0, (float) $order->getCustomerGroupDiscountRate());
        self::assertSame([60.0], $this->linePrices($order), 'the lines are priced for the group the order records');
    }

    public function testAGuestAccountPutInAGroupOrdersWithTheDefaultGroup(): void
    {
        [$customer, $currency, $lang, $product] = $this->createCatalogFixtures(basePrice: 100.0);
        $this->factory->assignCustomerGroup($customer, $this->factory->customerGroup('not-for-guests', 15.0));
        $customer->setIsGuest(1)->save();
        $default = CustomerGroupQuery::findDefault();

        $order = $this->createOrderThroughFacade($customer, $currency, $lang, $product);

        self::assertSame($default->getId(), $order->getCustomerGroupId());
        self::assertSame($default->effectiveDiscountRate(), (float) $order->getCustomerGroupDiscountRate());
        self::assertSame([100.0 * (1 - $default->effectiveDiscountRate() / 100)], $this->linePrices($order));
    }

    public function testADiscountOfTheCustomerAboveAHundredCountsForNothingAndIsRecordedAsSuch(): void
    {
        [$customer, $currency, $lang, $product] = $this->createCatalogFixtures();
        $customer->setDiscount('150')->save();

        $order = $this->createOrderThroughFacade($customer, $currency, $lang, $product);

        self::assertSame(0.0, (float) $order->getCustomerDiscountRate());
    }

    public function testCustomerOfTheDefaultGroupOrdersWithTheDefaultGroup(): void
    {
        [$customer, $currency, $lang, $product] = $this->createCatalogFixtures();
        $default = CustomerGroupQuery::findDefault();

        $order = $this->createOrderThroughFacade($customer, $currency, $lang, $product);

        self::assertSame($default->getId(), $order->getCustomerGroupId());
        self::assertSame((float) $default->getDiscount(), (float) $order->getCustomerGroupDiscountRate());
        self::assertSame((int) $default->getDisplayTaxedPrice(), $order->getCustomerGroupDisplayTaxedPrice());
    }

    /**
     * @return array{0: Customer, 1: Currency, 2: Lang, 3: Product}
     */
    private function createCatalogFixtures(?float $basePrice = null): array
    {
        $currency = $this->factory->currency();
        $lang = $this->factory->lang();
        $customer = $this->factory->customer($this->factory->customerTitle());
        $category = $this->factory->category();
        $taxRule = $this->factory->taxRule();
        $product = $this->factory->product($category, $taxRule, $currency, ['baseQuantity' => 100] + (null === $basePrice ? [] : ['basePrice' => $basePrice]));

        return [$customer, $currency, $lang, $product];
    }

    /**
     * The lines are priced for the customer the cart names, as the cart does when a line is
     * added, unless a price is given: that one is written as it is, and may be one the
     * buyer's group no longer owes.
     */
    private function createOrderThroughFacade(
        Customer $customer,
        Currency $currency,
        Lang $lang,
        Product $product,
        ?string $linePrice = null,
    ): Order {
        return $this->placeOrder($this->cartOf($customer, $currency, $product, $linePrice), $customer, $currency, $lang);
    }

    private function cartOf(Customer $customer, Currency $currency, Product $product, ?string $linePrice = null): Cart
    {
        $productSaleElements = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->findOne();
        self::assertNotNull($productSaleElements);

        $cart = new Cart();
        $cart->setCustomerId($customer->getId());
        $cart->setCurrencyId($currency->getId());
        $cart->setToken(uniqid('customer-discount-rate-test-', true));
        $cart->save();

        $cartItem = new CartItem();
        $cartItem
            ->setCartId($cart->getId())
            ->setProductId($product->getId())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setQuantity(1)
            ->setPrice($linePrice ?? '9.00')
            ->setPromoPrice($linePrice ?? '9.00')
            ->setPromo(0)
            ->save();

        if (null === $linePrice) {
            $this->getService(CartLinePricer::class)->reprice($cart, $currency, $cart->getCustomer());
        }

        return $cart;
    }

    /**
     * The cart as the next request reads it: a bulk update leaves the pooled objects on the
     * group the customer was in, and the services remember the answer they gave for the
     * request.
     */
    private function reloaded(Cart $cart): Cart
    {
        $this->getService('services_resetter')->reset();
        CustomerTableMap::clearInstancePool();
        CartTableMap::clearInstancePool();

        return CartQuery::create()->findPk($cart->getId());
    }

    private function placeOrder(Cart $cart, Customer $customer, Currency $currency, Lang $lang): Order
    {
        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($deliveryModule);
        self::assertNotNull($paymentModule);

        $sessionOrder = new Order();
        $sessionOrder
            ->setDeliveryOrderAddressId($this->factory->orderAddress()->getId())
            ->setInvoiceOrderAddressId($this->factory->orderAddress()->getId())
            ->setStatusId(OrderStatusQuery::getNotPaidStatus()->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($paymentModule->getId())
            ->setPostage('0')
            ->setPostageTax('0');

        return $this->orderFacade->createOrder(
            $this->dispatcher,
            $sessionOrder,
            $currency,
            $lang,
            $cart,
            $customer,
            useOrderDefinedAddresses: true,
        );
    }

    /**
     * @return list<float> the untaxed prices of the lines of the order
     */
    private function linePrices(Order $order): array
    {
        return array_map(
            static fn ($line): float => round((float) $line->getPrice(), 6),
            iterator_to_array(OrderProductQuery::create()->filterByOrderId($order->getId())->find(), false),
        );
    }
}
