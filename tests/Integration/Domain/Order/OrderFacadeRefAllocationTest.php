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
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * Covers the ref allocation wiring of OrderFacade::createOrder(): refs come
 * from the gapless order_ref sequence, whatever the creation path.
 *
 * The rollback-leaves-no-gap property itself is proven with real transactions
 * in {@see \Thelia\Tests\Integration\Domain\Sequence\GaplessSequenceGeneratorTest};
 * it cannot be observed here because the test wrapper transaction turns the
 * facade's inner rollback into a no-op.
 */
final class OrderFacadeRefAllocationTest extends ActionIntegrationTestCase
{
    private OrderFacade $orderFacade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderFacade = $this->getService(OrderFacade::class);
    }

    public function testFacadeOrdersGetConsecutiveSequenceRefs(): void
    {
        [$customer, $currency, $lang, $product] = $this->createCatalogFixtures();

        $firstOrder = $this->createOrderThroughFacade($customer, $currency, $lang, $product);
        $secondOrder = $this->createOrderThroughFacade($customer, $currency, $lang, $product);

        self::assertMatchesRegularExpression('/^ORD\d{12,}$/', $firstOrder->getRef());
        self::assertSame(
            $this->refNumber($firstOrder->getRef()) + 1,
            $this->refNumber($secondOrder->getRef()),
            'Two consecutive checkouts must produce consecutive refs.',
        );
    }

    public function testDirectModelInsertionSharesTheSameSequence(): void
    {
        [$customer, $currency, $lang, $product] = $this->createCatalogFixtures();

        $facadeOrder = $this->createOrderThroughFacade($customer, $currency, $lang, $product);
        $legacyOrder = $this->factory->order($customer);

        self::assertSame(
            $this->refNumber($facadeOrder->getRef()) + 1,
            $this->refNumber($legacyOrder->getRef()),
            'postInsert fallback (imports, modules) must consume the same sequence as the facade — otherwise refs would collide.',
        );
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
        $cart->setToken(uniqid('facade-ref-test-', true));
        $cart->save();

        $cartItem = new CartItem();
        $cartItem
            ->setCartId($cart->getId())
            ->setProductId($product->getId())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setQuantity(1)
            ->setPrice('10.00')
            ->setPromoPrice('10.00')
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

    private function refNumber(string $ref): int
    {
        self::assertMatchesRegularExpression('/^ORD\d+$/', $ref);

        return (int) substr($ref, 3);
    }
}
