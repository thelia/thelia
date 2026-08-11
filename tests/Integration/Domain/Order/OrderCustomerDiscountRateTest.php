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

use Thelia\Domain\Order\OrderFacade;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The customer discount is baked into the cart item prices, so the rate itself
 * has to be stored on the order: an invoice printed later must show the rate that
 * actually produced its prices, not the customer's current one.
 */
final class OrderCustomerDiscountRateTest extends ActionIntegrationTestCase
{
    private OrderFacade $orderFacade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderFacade = $this->getService(OrderFacade::class);
    }

    public function testOrderKeepsTheCustomerDiscountRateItWasPlacedWith(): void
    {
        [$customer, $currency, $lang, $product] = $this->createCatalogFixtures();

        $customer->setDiscount('10.000000')->save();

        $order = $this->createOrderThroughFacade($customer, $currency, $lang, $product);

        self::assertSame(
            10.0,
            (float) $order->getCustomerDiscountRate(),
            'The customer discount rate in force must be copied on the order.',
        );

        $customer->setDiscount('25.000000')->save();

        OrderTableMap::clearInstancePool();
        $reloadedOrder = OrderQuery::create()->findPk($order->getId());
        self::assertNotNull($reloadedOrder);
        self::assertSame(
            10.0,
            (float) $reloadedOrder->getCustomerDiscountRate(),
            'Changing the customer discount must not rewrite the rate of an already placed order.',
        );
    }

    public function testOrderOfACustomerWithoutDiscountStoresAZeroRate(): void
    {
        [$customer, $currency, $lang, $product] = $this->createCatalogFixtures();

        $order = $this->createOrderThroughFacade($customer, $currency, $lang, $product);

        self::assertSame(0.0, (float) $order->getCustomerDiscountRate());
    }

    /**
     * @return array{0: Customer, 1: Currency, 2: Lang, 3: Product}
     */
    private function createCatalogFixtures(): array
    {
        $currency = $this->factory->currency();
        $lang = $this->factory->lang();
        $customer = $this->factory->customer($this->factory->customerTitle());
        $category = $this->factory->category();
        $taxRule = $this->factory->taxRule();
        $product = $this->factory->product($category, $taxRule, $currency, ['baseQuantity' => 100]);

        return [$customer, $currency, $lang, $product];
    }

    private function createOrderThroughFacade(
        Customer $customer,
        Currency $currency,
        Lang $lang,
        Product $product,
    ): Order {
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
            ->setPrice('9.00')
            ->setPromoPrice('9.00')
            ->setPromo(0)
            ->save();

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
}
