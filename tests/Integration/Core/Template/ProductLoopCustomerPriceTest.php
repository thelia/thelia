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

namespace Thelia\Tests\Integration\Core\Template;

use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Tests\Support\Trait\ResetsPriceMemos;

/**
 * The product loops charge a customer what the customer price context says: the price of
 * the group of the customer, then the discount of the group and the one of the customer,
 * multiplied. A visitor and the back office read the public prices.
 *
 * Catalog price 100.00 untaxed, 20 % VAT.
 */
final class ProductLoopCustomerPriceTest extends IntegrationTestCase
{
    use LogsInAsCustomer;
    use ResetsPriceMemos;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::getDefaultCurrency();
        $this->resetPriceMemos();
    }

    protected function tearDown(): void
    {
        $this->resetPriceMemos();

        parent::tearDown();
    }

    public function testAVisitorReadsThePublicPrice(): void
    {
        $product = $this->catalogProduct();

        $row = $this->productRow($product);

        self::assertEqualsWithDelta(100.0, (float) $row['PRICE'], 0.000001);
        self::assertEqualsWithDelta(120.0, (float) $row['TAXED_PRICE'], 0.000001);
        self::assertSame(0, (int) $row['IS_PROMO']);
    }

    public function testAMemberReadsTheDiscountOfTheirGroupThenTheirOwn(): void
    {
        $product = $this->catalogProduct();
        $member = $this->member(15.0, 10.0);
        $this->loginAsCustomerInSession($member);

        $row = $this->productRow($product);

        // 100 x (1 - 15 %) x (1 - 10 %), never 100 x (1 - 25 %).
        self::assertEqualsWithDelta(76.5, (float) $row['PRICE'], 0.000001);
        self::assertEqualsWithDelta(76.5, (float) $row['PROMO_PRICE'], 0.000001);
        self::assertEqualsWithDelta(76.5, (float) $row['BEST_PRICE'], 0.000001);
        self::assertEqualsWithDelta(91.8, (float) $row['TAXED_PRICE'], 0.000001);
        self::assertEqualsWithDelta(15.3, (float) $row['BEST_PRICE_TAX'], 0.000001);
        self::assertSame(0, (int) $row['IS_PROMO']);
    }

    public function testAMemberReadsThePriceOfTheirGroupThenTheirOwnDiscount(): void
    {
        $product = $this->catalogProduct();
        $member = $this->member(15.0, 10.0);
        $this->factory()->customerGroupProductPrice($member->getCustomerGroup(), $this->defaultSaleElementsOf($product), $this->currency, 42.0);
        $this->loginAsCustomerInSession($member);

        $row = $this->productRow($product);

        self::assertEqualsWithDelta(37.8, (float) $row['PRICE'], 0.000001);
        self::assertEqualsWithDelta(37.8, (float) $row['BEST_PRICE'], 0.000001);
    }

    public function testTwoMembersOfTwoGroupsAreEachChargedTheirOwnPrice(): void
    {
        $product = $this->catalogProduct();
        $first = $this->member(20.0);
        $second = $this->member(50.0);

        $this->loginAsCustomerInSession($first);
        self::assertEqualsWithDelta(80.0, (float) $this->productRow($product)['PRICE'], 0.000001);

        $this->loginAsCustomerInSession($second);
        self::assertEqualsWithDelta(50.0, (float) $this->productRow($product)['PRICE'], 0.000001);

        $this->logoutCustomer();
        self::assertEqualsWithDelta(100.0, (float) $this->productRow($product)['PRICE'], 0.000001, 'A visitor follows, and reads the public price.');
    }

    public function testTheBackOfficeReadsTheCatalogWhoeverIsSignedIn(): void
    {
        $product = $this->catalogProduct();
        $this->loginAsCustomerInSession($this->member(15.0, 10.0));

        $row = $this->productRow($product, ['backend_context' => 1]);

        self::assertEqualsWithDelta(100.0, (float) $row['PRICE'], 0.000001);
        self::assertSame(0, (int) $row['IS_PROMO']);

        $row = $this->firstRow('product_sale_elements', ['id' => $this->defaultSaleElementsOf($product)->getId(), 'backend_context' => 1]);
        self::assertEqualsWithDelta(100.0, (float) $row['PRICE'], 0.000001);
    }

    public function testTheSaleElementLoopChargesAMemberTheSamePrice(): void
    {
        $product = $this->catalogProduct();
        $saleElement = $this->defaultSaleElementsOf($product);
        $member = $this->member(15.0, 10.0);
        $this->loginAsCustomerInSession($member);

        $row = $this->firstRow('product_sale_elements', ['id' => $saleElement->getId()]);

        self::assertEqualsWithDelta(76.5, (float) $row['PRICE'], 0.000001);
        self::assertEqualsWithDelta(91.8, (float) $row['TAXED_PRICE'], 0.000001);
        self::assertSame(0, (int) $row['IS_PROMO']);

        $this->factory()->customerGroupProductPrice($member->getCustomerGroup(), $saleElement, $this->currency, 42.0, 30.0);
        $this->resetPriceMemos();

        $row = $this->firstRow('product_sale_elements', ['id' => $saleElement->getId()]);

        // The price the promotion is struck against, and the price charged while it lasts.
        self::assertEqualsWithDelta(37.8, (float) $row['PRICE'], 0.000001);
        self::assertEqualsWithDelta(27.0, (float) $row['PROMO_PRICE'], 0.000001);
        self::assertSame(1, (int) $row['IS_PROMO']);
    }

    public function testTheCurrencyOfTheLoopIsTheOneTheDiscountsApplyIn(): void
    {
        $product = $this->catalogProduct();
        $other = $this->factory()->currency(['code' => 'QQQ', 'symbol' => 'Q', 'rate' => 2.0, 'byDefault' => 0]);
        $this->factory()->productPrice($this->defaultSaleElementsOf($product), $other, ['price' => '0.000000', 'promoPrice' => '0.000000', 'fromDefaultCurrency' => true]);
        $this->loginAsCustomerInSession($this->member(20.0));

        $converted = 100.0 * 2.0 / (float) $this->currency->getRate();

        self::assertEqualsWithDelta($converted * 0.8, (float) $this->productRow($product, ['currency' => $other->getId()])['PRICE'], 0.000001);
        self::assertEqualsWithDelta($converted * 0.8, (float) $this->firstRow('product_sale_elements', ['id' => $this->defaultSaleElementsOf($product)->getId(), 'currency' => $other->getId()])['PRICE'], 0.000001);
    }

    public function testTheComplexLoopChargesAMemberTheirPrice(): void
    {
        $product = $this->catalogProduct();
        $this->loginAsCustomerInSession($this->member(15.0, 10.0));

        $row = $this->productRow($product, ['complex' => 1]);

        self::assertEqualsWithDelta(76.5, (float) $row['BEST_PRICE'], 0.000001);
        self::assertSame(0, (int) $row['IS_PROMO']);

        $this->logoutCustomer();
        self::assertEqualsWithDelta(100.0, (float) $this->productRow($product, ['complex' => 1])['BEST_PRICE'], 0.000001);
    }

    public function testTheComplexLoopPricesAProductByItsCheapestCombinationForTheMember(): void
    {
        $product = $this->catalogProduct();
        $cheaper = $this->factory()->productSaleElement($product, ['quantity' => 5]);
        $this->factory()->productPrice($cheaper, $this->currency, ['price' => '60.000000', 'promoPrice' => '60.000000']);
        $member = $this->member(50.0);
        $this->factory()->customerGroupProductPrice($member->getCustomerGroup(), $this->defaultSaleElementsOf($product), $this->currency, 20.0);

        self::assertEqualsWithDelta(60.0, (float) $this->productRow($product, ['complex' => 1])['BEST_PRICE'], 0.000001, 'A visitor: the cheapest combination of the catalog.');

        $this->loginAsCustomerInSession($member);

        // The default combination is 20 for the group, the other 60 less 50 %: 20 wins.
        self::assertEqualsWithDelta(20.0, (float) $this->productRow($product, ['complex' => 1])['BEST_PRICE'], 0.000001);
    }

    public function testTheTaxOfTheBestPriceIsTheTaxOfThePriceChargedNotOfThePromoPriceOfAProductNotOnPromotion(): void
    {
        $product = $this->catalogProduct();
        // A promo price lying in the catalog of a combination that is not on promotion.
        ProductPriceQuery::create()
            ->filterByProductSaleElementsId($this->defaultSaleElementsOf($product)->getId())
            ->findOne()
            ->setPromoPrice('50.000000')
            ->save($this->getPropelConnection());

        $row = $this->productRow($product);

        self::assertSame(0, (int) $row['IS_PROMO']);
        self::assertEqualsWithDelta(100.0, (float) $row['BEST_PRICE'], 0.000001);
        self::assertEqualsWithDelta(20.0, (float) $row['BEST_PRICE_TAX'], 0.000001);
    }

    public function testTheMaxPriceOfTheComplexLoopConvertsAPriceDerivedFromTheDefaultCurrency(): void
    {
        $product = $this->catalogProduct();
        $dollar = $this->factory()->currency(['code' => 'QQQ', 'symbol' => '$', 'rate' => 2.0, 'byDefault' => 0]);
        $derived = (new ProductPrice())
            ->setProductSaleElementsId($this->defaultSaleElementsOf($product)->getId())
            ->setCurrencyId($dollar->getId())
            ->setPrice('0.000000')
            ->setPromoPrice('0.000000')
            ->setFromDefaultCurrency(true);
        $derived->save($this->getPropelConnection());
        $arguments = ['id' => $product->getId(), 'complex' => 1, 'currency' => $dollar->getId()];

        // 100 in the default currency, 200 once converted: the row of the dollar says 0.
        self::assertNotNull($this->firstRow('product', $arguments + ['max_price' => 250]));
        self::assertNull($this->firstRow('product', $arguments + ['max_price' => 150]));
    }

    /**
     * Frozen limit: the orders and the price filters of the loop run in SQL, on the public
     * prices. What a group or a customer alone is owed is resolved per customer in PHP, and
     * is not in the database.
     */
    public function testThePriceOrderAndTheMaxPriceFilterStayOnThePublicPrices(): void
    {
        $dear = $this->catalogProduct(100.0);
        $cheap = $this->catalogProduct(60.0);
        $member = $this->member(0.0);
        $this->factory()->customerGroupProductPrice($member->getCustomerGroup(), $this->defaultSaleElementsOf($dear), $this->currency, 42.0);
        $this->loginAsCustomerInSession($member);
        $ids = $dear->getId().','.$cheap->getId();

        self::assertSame([$cheap->getId(), $dear->getId()], $this->productIds(['id' => $ids, 'order' => 'min_price']));
        self::assertSame([$cheap->getId()], $this->productIds(['id' => $ids, 'max_price' => 70]), 'The product the member pays 42 for is out of a 70 maximum: it is 100 in the catalog.');
        self::assertEqualsWithDelta(42.0, (float) $this->productRow($dear)['PRICE'], 0.000001, 'The card shows what the member pays.');
    }

    private function member(float $groupDiscount, float $customerDiscount = 0.0): Customer
    {
        $factory = $this->factory();
        $member = $factory->customer($factory->customerTitle(), ['customerGroup' => $factory->customerGroup(discount: $groupDiscount)]);
        $member->setDiscount((string) $customerDiscount)->save($this->getPropelConnection());

        return $member;
    }

    private function catalogProduct(float $basePrice = 100.0): Product
    {
        $factory = $this->factory();
        // A non-empty override array forces a rule of its own instead of reusing the seeded one.
        $taxRule = $factory->taxRule(['isDefault' => false]);
        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId(Country::getDefaultCountry()->getId())
            ->setTaxId($factory->tax(['requirements' => ['percent' => '20']])->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        return $factory->product($factory->category(), $taxRule, $this->currency, ['basePrice' => $basePrice, 'baseQuantity' => 100, 'title' => 'Customer price product']);
    }

    private function defaultSaleElementsOf(Product $product): ProductSaleElements
    {
        return $product->getDefaultSaleElements();
    }

    private function factory(): \Thelia\Test\FixtureFactory
    {
        return $this->createFixtureFactory();
    }

    private function productRow(Product $product, array $arguments = []): array
    {
        $row = $this->firstRow('product', $arguments + ['id' => $product->getId()]);
        self::assertNotNull($row);

        return $row;
    }

    private function firstRow(string $loop, array $arguments): ?array
    {
        foreach ($this->getService(LoopExecutor::class)->execute($loop, $arguments) as $row) {
            return $row->getVarVal();
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function productIds(array $arguments): array
    {
        $ids = [];

        foreach ($this->getService(LoopExecutor::class)->execute('product', $arguments) as $row) {
            $ids[] = (int) $row->getVarVal()['ID'];
        }

        return $ids;
    }
}
