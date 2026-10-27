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

namespace Thelia\Tests\Integration\Api;

use Thelia\Api\Service\DataAccess\ProductSaleElementsAccessService;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Support\Trait\ResetsPriceMemos;

/**
 * The sale elements the product page of a theme offers are priced for the customer of the
 * session: the price of their group, then the discounts of the group and of the customer,
 * what their cart will charge. A sale element nothing prices in the currency is not offered,
 * and never a failure.
 *
 * Catalog price 100.00 untaxed, 20 % VAT.
 */
final class ProductSaleElementsAccessServiceCustomerPriceTest extends IntegrationTestCase
{
    use LogsInAsCustomer;
    use RecordsSqlQueries;
    use ResetsPriceMemos;

    private Currency $currency;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->currency = Currency::getDefaultCurrency();
        $this->resetPriceMemos();
    }

    protected function tearDown(): void
    {
        $this->resetPriceMemos();

        parent::tearDown();
    }

    public function testAVisitorIsOfferedThePublicPrice(): void
    {
        $product = $this->catalogProduct();

        $offered = $this->offered($product);

        self::assertCount(1, $offered);
        self::assertEqualsWithDelta(100.0, $offered[0]['untaxedPrice'], 0.000001);
        self::assertEqualsWithDelta(120.0, $offered[0]['price'], 0.000001);
        self::assertFalse($offered[0]['isPromo']);
        self::assertNull($offered[0]['displayInitialPrice']);
    }

    public function testAMemberIsOfferedTheDiscountsOfTheirGroupThenTheirOwn(): void
    {
        $product = $this->catalogProduct();
        $this->loginAsCustomerInSession($this->member(15.0, 10.0));

        $offered = $this->offered($product);

        self::assertEqualsWithDelta(76.5, $offered[0]['untaxedPrice'], 0.000001);
        self::assertEqualsWithDelta(76.5, $offered[0]['promoUntaxedPrice'], 0.000001);
        self::assertEqualsWithDelta(91.8, $offered[0]['price'], 0.000001);
        self::assertFalse($offered[0]['isPromo']);
    }

    public function testAMemberIsOfferedThePriceOfTheirGroupAndItsPromotion(): void
    {
        $product = $this->catalogProduct();
        $member = $this->member(15.0, 10.0);
        $this->factory->customerGroupProductPrice($member->getCustomerGroup(), $product->getDefaultSaleElements(), $this->currency, 42.0, 30.0);
        $this->loginAsCustomerInSession($member);

        $offered = $this->offered($product);

        self::assertEqualsWithDelta(37.8, $offered[0]['untaxedPrice'], 0.000001);
        self::assertEqualsWithDelta(27.0, $offered[0]['promoUntaxedPrice'], 0.000001);
        self::assertEqualsWithDelta(32.4, $offered[0]['promoPrice'], 0.000001);
        self::assertTrue($offered[0]['isPromo']);
        self::assertTrue($offered[0]['displayInitialPrice']);
    }

    public function testASaleElementWithoutAPriceIsLeftOutInsteadOfFailingThePage(): void
    {
        $product = $this->catalogProduct();
        $priced = $this->factory->productSaleElement($product, ['quantity' => 5]);
        $this->factory->productPrice($priced, $this->currency, ['price' => '80.000000', 'promoPrice' => '80.000000']);
        $this->factory->productSaleElement($product, ['quantity' => 5]);

        $offered = $this->offered($product);

        self::assertSame([$product->getDefaultSaleElements()->getId(), $priced->getId()], array_column($offered, 'id'));
    }

    public function testAProductNothingPricesIsOfferedNothing(): void
    {
        $product = $this->catalogProduct();
        $product->getDefaultSaleElements()->getProductPrices()->delete($this->getPropelConnection());

        self::assertSame([], $this->offered($product));
    }

    public function testTheStatementsDoNotGrowWithTheSaleElements(): void
    {
        $product = $this->catalogProduct();
        $member = $this->member(15.0, 10.0);
        $this->loginAsCustomerInSession($member);

        $count = function () use ($product): int {
            $this->resetPriceMemos();

            return self::countSqlQueriesSelectingFrom($this->recordSqlQueries(function () use ($product): void {
                $this->offered($product);
            }), 'product_price');
        };

        $one = $count();

        for ($i = 0; $i < 4; ++$i) {
            $this->factory->productPrice($this->factory->productSaleElement($product), $this->currency);
        }

        self::assertSame($one, $count(), 'The prices of all the sale elements of a product are read once.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function offered(Product $product): array
    {
        return json_decode((string) $this->getService(ProductSaleElementsAccessService::class)->psesByProduct($product->getId()), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function member(float $groupDiscount, float $customerDiscount = 0.0): Customer
    {
        $member = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $this->factory->customerGroup(discount: $groupDiscount)]);
        $member->setDiscount((string) $customerDiscount)->save($this->getPropelConnection());

        return $member;
    }

    private function catalogProduct(): Product
    {
        $taxRule = $this->factory->taxRule(['isDefault' => false]);
        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId(Country::getDefaultCountry()->getId())
            ->setTaxId($this->factory->tax(['requirements' => ['percent' => '20']])->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        return $this->factory->product($this->factory->category(), $taxRule, $this->currency, ['basePrice' => 100.0, 'baseQuantity' => 100, 'title' => 'Offered product']);
    }
}
