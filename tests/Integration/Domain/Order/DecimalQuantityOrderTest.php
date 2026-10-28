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

use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Operators;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Domain\Catalog\Quantity\QuantityFormatter;
use Thelia\Domain\Catalog\Quantity\QuantityRule;
use Thelia\Domain\Catalog\Quantity\UnitCode;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Domain\Promotion\Coupon\Type\CouponAbstract;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Model\Cart;
use Thelia\Model\Category;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Tests\Integration\Domain\Promotion\ActionIntegrationPromotionTestCase;

/**
 * An order of 1.25 kg: the line keeps the unit and the decimals it was sold with, the
 * totals and the taxes come out to the cent as the cart showed them, an amount per
 * article comes off once, and the stock gives exactly 1.25 kg.
 */
final class DecimalQuantityOrderTest extends ActionIntegrationPromotionTestCase
{
    private Country $country;
    private Category $category;
    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = $this->factory->country(['isocode' => '999', 'isoalpha2' => 'QZ', 'isoalpha3' => 'QZQ']);
        $this->category = $this->factory->category();
        $this->currency = $this->factory->currency();

        // Cheque keeps the stock until the payment; the order of this test takes it at once.
        $this->dispatcher->addListener(
            TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque'),
            static fn (ManageStockOnCreationEvent $event) => $event->setManageStock(true),
        );
    }

    public function testTheOrderLineKeepsTheUnitAndTheDecimalsItWasSoldWith(): void
    {
        [$product, $combination] = $this->weighedProduct();
        $cart = $this->cartOf($product, $combination, 1.25);

        $line = $this->onlyLineOf($this->placeOrder($cart));

        // The combination is sold by the piece from now on: the order line does not follow.
        (new QuantityRule())->applyTo($combination);
        $combination->save();
        OrderProductTableMap::clearInstancePool();
        $line = OrderProductQuery::create()->findPk($line->getId());

        self::assertSame('kg', $line?->getUnitCode());
        self::assertSame(2, $line->getQuantityDecimals());
        self::assertEqualsWithDelta(1.25, (float) $line->getQuantity(), 0.000001);
    }

    public function testAWeighedLineIsTotalledToTheCentWithItsTaxesAsTheCartShowedIt(): void
    {
        [$product, $combination] = $this->weighedProduct();
        $cart = $this->cartOf($product, $combination, 1.25);
        $cartTotal = $cart->getTaxedAmount($this->country);

        $order = $this->placeOrder($cart);
        $tax = 0.0;
        $total = $order->getTotalAmount($tax, includePostage: false);

        // 12.35 + 2.47 of tax, 1.25 times: 18.525, charged 18.53, of which 3.09 of tax.
        self::assertEqualsWithDelta(18.53, $cartTotal, 0.000001, 'Control: the cart shows 18.53.');
        self::assertEqualsWithDelta(18.53, $total, 0.000001, 'The order charges what the cart showed, to the cent.');
        self::assertEqualsWithDelta(3.09, $tax, 0.000001, 'The tax of the order is a whole number of cents.');
    }

    /**
     * The quantity column is a binary floating point number, which keeps 0.7 inexactly: the
     * order must not round 0.7 kg at 0.75 down to 0.52 where the cart showed 0.53.
     */
    public function testAQuantityTheColumnCannotKeepExactlyIsTotalledAsSold(): void
    {
        $product = $this->factory->product($this->category, $this->factory->taxRule(), $this->currency, ['baseQuantity' => 10, 'basePrice' => 0.75]);
        $combination = $this->defaultPseFor($product);
        (new QuantityRule(minQuantity: 0.1, step: 0.1, decimals: 1, unitCode: UnitCode::KILOGRAM))->applyTo($combination);
        $combination->save();
        $cart = $this->cartOf($product, $combination, 0.7);

        $total = $this->placeOrder($cart)->getTotalAmount(includePostage: false);

        self::assertEqualsWithDelta(0.53, $cart->getTaxedAmount($this->country), 0.000001, 'Control: the cart shows 0.53.');
        self::assertEqualsWithDelta(0.53, $total, 0.000001);
    }

    public function testAnAmountPerArticleComesOffAWeighedOrderOnce(): void
    {
        [$product, $combination] = $this->weighedProduct();
        $this->automaticPromotion(
            type: 'thelia.coupon.type.remove_amount_on_products',
            effects: $this->effectsSavedByTheBackOffice('thelia.coupon.type.remove_amount_on_products', [
                'amount' => '3',
                'category_id' => (string) $this->category->getId(),
                'products' => [(string) $product->getId()],
            ]),
            conditions: $this->atLeastOneArticle(),
        );
        $cart = $this->cartOf($product, $combination, 1.25);

        $order = $this->placeOrder($cart);
        $tax = 0.0;
        $total = $order->getTotalAmount($tax, includePostage: false);

        self::assertEqualsWithDelta(3.0, (float) $order->getDiscount(), 0.000001, 'Once for the line, not 1.25 times.');
        self::assertEqualsWithDelta(15.53, $total, 0.000001);
        self::assertEqualsWithDelta(round($cart->getTaxedAmount($this->country), 2), $total, 0.000001, 'The order charges what the cart showed.');
    }

    public function testTheStockGivesExactlyTheWeightOrdered(): void
    {
        [$product, $combination] = $this->weighedProduct(stock: 10.0);

        $this->placeOrder($this->cartOf($product, $combination, 1.25));
        $this->placeOrder($this->cartOf($product, $combination, 0.75));

        self::assertSame(8_000, $this->stockInGrams($combination));
    }

    public function testTheOrderProductLoopGivesTheUnitAndTheDecimalsOfTheLine(): void
    {
        [$product, $combination] = $this->weighedProduct();
        $order = $this->placeOrder($this->cartOf($product, $combination, 1.25));

        $row = $this->loopRowsOf($order)[0];

        self::assertEqualsWithDelta(1.25, $row['QUANTITY'], 0.000001);
        self::assertSame(2, $row['QUANTITY_DECIMALS']);
        self::assertSame('kg', $row['UNIT_CODE']);
        self::assertSame('1.25 kg', (new QuantityFormatter())->format((float) $row['QUANTITY'], $row['QUANTITY_DECIMALS'], UnitCode::tryFrom($row['UNIT_CODE']), 'en_US'));
    }

    /**
     * An order placed before the rules existed has lines with no unit and no decimals:
     * its quantities, its totals and its taxes read as they did, so its invoice comes out
     * the same.
     */
    public function testAnOrderPlacedBeforeTheRulesReadsAsItDid(): void
    {
        $order = $this->factory->order();
        $line = (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef('LEGACY')
            ->setProductSaleElementsRef('LEGACY-PSE')
            ->setTitle('Placed long ago')
            ->setQuantity(3)
            ->setPrice('12.350000')
            ->setPromoPrice('0.000000')
            ->setWasNew(0)
            ->setWasInPromo(0);
        $line->save($this->getPropelConnection());
        (new OrderProductTax())
            ->setOrderProductId($line->getId())
            ->setTitle('VAT 20')
            ->setAmount('2.470000')
            ->setPromoAmount('0.000000')
            ->save($this->getPropelConnection());

        $row = $this->loopRowsOf($order)[0];
        $tax = 0.0;
        $total = $order->getTotalAmount($tax, includePostage: false, includeDiscount: false);

        $line->reload();
        self::assertNull($line->getUnitCode());
        self::assertSame(0, $line->getQuantityDecimals());
        self::assertSame(3.0, $row['QUANTITY']);
        self::assertSame(0, $row['QUANTITY_DECIMALS']);
        self::assertSame('', $row['UNIT_CODE'], 'A loop writes an absent value as an empty string.');
        self::assertSame('3', (new QuantityFormatter())->format((float) $row['QUANTITY'], $row['QUANTITY_DECIMALS'], null, 'en_US'));
        self::assertEqualsWithDelta(44.46, $row['TOTAL_TAXED_PRICE'], 0.000001);
        self::assertEqualsWithDelta(37.05, $row['TOTAL_PRICE'], 0.000001);
        self::assertEqualsWithDelta(44.46, $total, 0.000001);
        self::assertEqualsWithDelta(7.41, $tax, 0.000001);
    }

    /**
     * @return array{0: Product, 1: ProductSaleElements}
     */
    private function weighedProduct(float $stock = 100.0): array
    {
        $product = $this->factory->product($this->category, $this->taxRuleAtTwentyPercent(), $this->currency, ['baseQuantity' => 0, 'basePrice' => 12.35]);
        $combination = $this->defaultPseFor($product);
        (new QuantityRule(minQuantity: 0.25, step: 0.25, decimals: 2, unitCode: UnitCode::KILOGRAM))->applyTo($combination);
        $combination->setQuantity($stock)->save();

        return [$product, $combination];
    }

    private function taxRuleAtTwentyPercent(): TaxRule
    {
        $taxRule = $this->factory->taxRule(['isDefault' => false]);
        $tax = $this->factory->tax(['requirements' => ['percent' => '20'], 'title' => 'VAT 20']);

        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId($this->country->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        return $taxRule;
    }

    private function cartOf(Product $product, ProductSaleElements $combination, float $quantity): Cart
    {
        $cart = $this->newEmptyCart();
        $this->dispatch(
            (new CartEvent($cart))
                ->setProductId($product->getId())
                ->setProductSaleElementsId($combination->getId())
                ->setQuantity($quantity)
                ->setNewness(true)
                ->setAppend(false),
            TheliaEvents::CART_ADDITEM,
        );
        $cart->reload();

        return $cart;
    }

    private function placeOrder(Cart $cart): Order
    {
        $customer = $this->signedInCustomer();
        $sessionOrder = $this->session()->getOrder();
        $sessionOrder
            ->setDeliveryOrderAddressId($this->factory->orderAddress(country: $this->country)->getId())
            ->setInvoiceOrderAddressId($this->factory->orderAddress(country: $this->country)->getId())
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
        $this->dispatch(new OrderEvent($order), TheliaEvents::ORDER_BEFORE_PAYMENT);

        return $order;
    }

    private function onlyLineOf(Order $order): OrderProduct
    {
        $lines = OrderProductQuery::create()->filterByOrderId($order->getId())->find();
        self::assertCount(1, $lines);

        return $lines->getFirst();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loopRowsOf(Order $order): array
    {
        $rows = [];
        foreach ($this->getService(LoopExecutor::class)->execute('order_product', ['order' => $order->getId()]) as $row) {
            $rows[] = $row->getVarVal();
        }
        self::assertCount(1, $rows);

        return $rows;
    }

    private function stockInGrams(ProductSaleElements $combination): int
    {
        ProductSaleElementsTableMap::clearInstancePool();

        return (int) round((float) ProductSaleElementsQuery::create()->findPk($combination->getId())?->getQuantity() * 1000);
    }

    private function signedInCustomer(): Customer
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->getService(SecurityContext::class)->setCustomerUser($customer);

        return $customer;
    }

    /**
     * @param array<string, mixed> $couponSpecific
     *
     * @return array<string, mixed>
     */
    private function effectsSavedByTheBackOffice(string $couponType, array $couponSpecific): array
    {
        $type = $this->getService($couponType);
        self::assertInstanceOf(CouponInterface::class, $type);

        return $type->getEffects([
            CouponAbstract::COUPON_DATASET_NAME => json_encode($couponSpecific, \JSON_THROW_ON_ERROR),
        ]);
    }

    private function atLeastOneArticle(): string
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
