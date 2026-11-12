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

namespace Thelia\Tests\Integration\Api\Service\DataAccess;

use Thelia\Api\Service\DataAccess\ProductSaleElementsAccessService;
use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Integration\Domain\Pricing\PriceList\PriceListFixtures;
use Thelia\Tests\Integration\Domain\Pricing\PriceVisibility\PriceDisplayFixtures;

/**
 * The sale elements the product page of a theme is built from say, for each of them, who is
 * shown the price (`priceDisplayMode`), whether it may be bought (`purchasable`) and the tiers
 * of the price list that prices it: and a price the visitor is not shown is in none of the
 * fields that carry it, the promotion included.
 *
 * Catalog price 100.00 untaxed, promotion 80.00, 20 % VAT: the amounts 100, 80, 120 and 96 are
 * the ones that must never be read behind a hidden price.
 */
final class ProductSaleElementsAccessPriceDisplayTest extends IntegrationTestCase
{
    use LogsInAsCustomer;
    use PriceDisplayFixtures;
    use PriceListFixtures;
    use RecordsSqlQueries;

    private const PRICE_FIELDS = ['price', 'untaxedPrice', 'promoPrice', 'promoUntaxedPrice'];

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->forgetPriceDisplayMemos();
    }

    protected function tearDown(): void
    {
        $this->logoutCustomer();
        $this->forgetPriceDisplayMemos();

        parent::tearDown();
    }

    public function testAShopThatShowsEveryPriceKeepsThemAndSaysSo(): void
    {
        $product = $this->promotedProduct();

        $offered = $this->offered($product);

        self::assertCount(1, $offered);
        self::assertSame('visible', $offered[0]['priceDisplayMode']);
        self::assertTrue($offered[0]['purchasable']);
        self::assertSame([], $offered[0]['priceTiers']);
        self::assertEqualsWithDelta(100.0, $offered[0]['untaxedPrice'], 0.000001);
        self::assertEqualsWithDelta(80.0, $offered[0]['promoUntaxedPrice'], 0.000001);
        self::assertEqualsWithDelta(120.0, $offered[0]['price'], 0.000001);
        self::assertEqualsWithDelta(96.0, $offered[0]['promoPrice'], 0.000001);
        self::assertTrue($offered[0]['isPromo']);
    }

    public function testAVisitorOfAShopThatShowsItsPricesToCustomersIsShownNoPriceNorPromotion(): void
    {
        $product = $this->promotedProduct();
        $this->shopShows(PriceDisplayMode::LoggedIn);

        $offered = $this->offered($product);

        self::assertCount(1, $offered, 'The sale element stays offered: the page shows the product without its price.');
        self::assertSame('logged_in', $offered[0]['priceDisplayMode']);
        self::assertFalse($offered[0]['purchasable']);
        self::assertSame([], $offered[0]['priceTiers']);
        $this->assertNoPrice($offered[0]);
        self::assertFalse($offered[0]['isPromo'], 'A promotion label would tell the price it hides.');
        self::assertNull($offered[0]['displayInitialPrice']);
        $this->assertNoAmountInTheJson($product);
    }

    public function testACustomerOfAShopThatShowsItsPricesToCustomersIsShownThem(): void
    {
        $product = $this->promotedProduct();
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $this->loginAsCustomerInSession($this->factory->customer($this->factory->customerTitle()));

        $offered = $this->offered($product);

        self::assertSame('logged_in', $offered[0]['priceDisplayMode']);
        self::assertTrue($offered[0]['purchasable']);
        self::assertEqualsWithDelta(100.0, $offered[0]['untaxedPrice'], 0.000001);
        self::assertEqualsWithDelta(96.0, $offered[0]['promoPrice'], 0.000001);
        self::assertTrue($offered[0]['isPromo']);
    }

    public function testNobodyIsShownThePriceOfAProductOnRequest(): void
    {
        $product = $this->promotedProduct();
        $this->productShows($product, PriceDisplayMode::OnRequest);

        foreach ([null, $this->factory->customer($this->factory->customerTitle())] as $customer) {
            $this->forgetPriceDisplayMemos();

            if ($customer instanceof Customer) {
                $this->loginAsCustomerInSession($customer);
            }

            $offered = $this->offered($product);

            self::assertSame('on_request', $offered[0]['priceDisplayMode']);
            self::assertFalse($offered[0]['purchasable']);
            self::assertSame([], $offered[0]['priceTiers']);
            $this->assertNoPrice($offered[0]);
            self::assertFalse($offered[0]['isPromo']);
            $this->assertNoAmountInTheJson($product);
        }
    }

    public function testTheModeOfAProductIsTheOneItStatesOverTheOneOfTheShop(): void
    {
        $hidden = $this->promotedProduct();
        $shown = $this->promotedProduct();
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $this->productShows($shown, PriceDisplayMode::Visible);

        self::assertSame('logged_in', $this->offered($hidden)[0]['priceDisplayMode']);
        self::assertNull($this->offered($hidden)[0]['untaxedPrice']);
        self::assertSame('visible', $this->offered($shown)[0]['priceDisplayMode']);
        self::assertEqualsWithDelta(100.0, $this->offered($shown)[0]['untaxedPrice'], 0.000001);
    }

    /**
     * The example of the brief: lines 1 -> 10 and 10 -> 9, a 20 % VAT on top.
     */
    public function testACustomerOfAPriceListIsToldItsTiersAndThePriceOfTheFirstOne(): void
    {
        $product = $this->promotedProduct();
        $pse = $product->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $list = $this->priceList('PRO');
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, '10', 1);
        $this->priceListItem($list, $pse, '9', 10);
        $this->loginAsCustomerInSession($this->memberOf($group));
        $this->forgetRequestMemos();

        $offered = $this->offered($product);

        self::assertTrue($offered[0]['purchasable']);
        self::assertEqualsWithDelta(10.0, $offered[0]['untaxedPrice'], 0.000001);
        self::assertCount(2, $offered[0]['priceTiers']);
        self::assertSame(1, $offered[0]['priceTiers'][0]['minQuantity']);
        self::assertEqualsWithDelta(10.0, $offered[0]['priceTiers'][0]['price'], 0.000001);
        self::assertEqualsWithDelta(10.0, $offered[0]['priceTiers'][0]['promoPrice'], 0.000001);
        self::assertEqualsWithDelta(12.0, $offered[0]['priceTiers'][0]['taxedPrice'], 0.000001);
        self::assertSame(10, $offered[0]['priceTiers'][1]['minQuantity']);
        self::assertEqualsWithDelta(9.0, $offered[0]['priceTiers'][1]['price'], 0.000001);
        self::assertEqualsWithDelta(10.8, $offered[0]['priceTiers'][1]['taxedPrice'], 0.000001);
    }

    public function testTheTiersOfAPriceListAreNotToldToAVisitorWhoIsShownNoPrice(): void
    {
        $product = $this->promotedProduct();
        $pse = $product->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $list = $this->priceList('PRO');
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, '9', 10);
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $this->loginAsCustomerInSession($this->memberOf($group));
        $this->logoutCustomer();
        $this->forgetRequestMemos();

        $offered = $this->offered($product);

        self::assertSame([], $offered[0]['priceTiers']);
        $this->assertNoPrice($offered[0]);
    }

    public function testASaleElementAnExclusivePriceListDoesNotPriceIsShownNotSold(): void
    {
        $product = $this->promotedProduct();
        $other = $this->factory->productSaleElement($product, ['quantity' => 5]);
        $this->factory->productPrice($other, Currency::getDefaultCurrency(), ['price' => '50.000000', 'promoPrice' => '50.000000']);
        $group = $this->factory->customerGroup();
        $list = $this->priceList('EXCL', exclusive: true);
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $product->getDefaultSaleElements(), '70', 1);
        $this->loginAsCustomerInSession($this->memberOf($group));
        $this->forgetRequestMemos();

        $offered = array_column($this->offered($product), null, 'id');

        self::assertTrue($offered[$product->getDefaultSaleElements()->getId()]['purchasable']);
        self::assertFalse($offered[$other->getId()]['purchasable']);
        self::assertSame([], $offered[$other->getId()]['priceTiers']);
    }

    /**
     * A price list prices the customer, and the product is on request all the same: what the list
     * says about the price (a struck-through catalog price, the tiers) is no more read than the
     * price.
     */
    public function testAPriceListDoesNotReopenAPriceThatIsOnRequest(): void
    {
        $product = $this->promotedProduct();
        $pse = $product->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $list = $this->priceList('PRO');
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, '70', 1);
        $this->priceListItem($list, $pse, '60', 10);
        $this->productShows($product, PriceDisplayMode::OnRequest);
        $this->loginAsCustomerInSession($this->memberOf($group));
        $this->forgetRequestMemos();

        $offered = $this->offered($product);

        $this->assertNoPrice($offered[0]);
        self::assertFalse($offered[0]['isPromo']);
        self::assertNull($offered[0]['displayInitialPrice']);
        self::assertSame([], $offered[0]['priceTiers']);
        self::assertFalse($offered[0]['purchasable']);
        self::assertDoesNotMatchRegularExpression('/:\s*(70|60|84|72)(\.0+)?\b/', (string) $this->getService(ProductSaleElementsAccessService::class)->psesByProduct($product->getId()));
    }

    public function testTheStatementsDoNotGrowWithTheSaleElementsWhateverTheMode(): void
    {
        $product = $this->promotedProduct();
        $group = $this->factory->customerGroup();
        $list = $this->priceList('PRO');
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $product->getDefaultSaleElements(), '9', 10);
        $this->shopShows(PriceDisplayMode::LoggedIn);

        $count = function (?Customer $customer) use ($product): int {
            $this->forgetRequestMemos();

            if ($customer instanceof Customer) {
                $this->loginAsCustomerInSession($customer);
            } else {
                $this->logoutCustomer();
            }

            $statements = $this->recordSqlQueries(function () use ($product): void {
                $this->offered($product);
            });

            // What the prices and their visibility read: the combinations of a sale element are
            // read one by one by the page since before, and are not the subject.
            $reads = 0;

            foreach (['product', 'product_price', 'price_list', 'price_list_item', 'price_list_assignment'] as $table) {
                $reads += self::countSqlQueriesSelectingFrom($statements, $table);
            }

            return $reads;
        };
        $member = $this->memberOf($group);

        // The first read of a process pays for what the next ones find in memory.
        $count(null);
        $count($member);
        $visitorOne = $count(null);
        $memberOne = $count($member);

        for ($i = 0; $i < 4; ++$i) {
            $this->factory->productPrice($this->factory->productSaleElement($product), Currency::getDefaultCurrency());
        }

        self::assertSame($visitorOne, $count(null), 'A hidden price costs nothing per sale element.');
        self::assertSame($memberOne, $count($member), 'The tiers and the modes of the sale elements are read once.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function offered(Product $product): array
    {
        return json_decode((string) $this->getService(ProductSaleElementsAccessService::class)->psesByProduct($product->getId()), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $saleElement
     */
    private function assertNoPrice(array $saleElement): void
    {
        foreach (self::PRICE_FIELDS as $field) {
            self::assertNull($saleElement[$field] ?? null, $field.' must be hidden.');
        }
    }

    /**
     * What a page prints is the JSON of the sale elements: none of the amounts is in it.
     */
    private function assertNoAmountInTheJson(Product $product): void
    {
        $json = (string) $this->getService(ProductSaleElementsAccessService::class)->psesByProduct($product->getId());

        foreach (['100', '80', '120', '96'] as $amount) {
            self::assertDoesNotMatchRegularExpression('/:\s*'.$amount.'(\.0+)?\b/', $json, 'The amount '.$amount.' is readable.');
        }
    }

    private function promotedProduct(): Product
    {
        $product = $this->factory->product(
            $this->factory->category(),
            $this->twentyPercentTaxRule(),
            Currency::getDefaultCurrency(),
            ['basePrice' => 100.0, 'baseQuantity' => 37, 'title' => 'Offered product'],
        );
        $pse = $product->getDefaultSaleElements();
        $price = $pse->getProductPrices()->getFirst();
        $price->setPromoPrice('80.000000')->save($this->getPropelConnection());
        $pse->setPromo(1)->save($this->getPropelConnection());

        return $product;
    }

    private function twentyPercentTaxRule(): TaxRule
    {
        $taxRule = $this->factory->taxRule(['isDefault' => false]);
        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId(Country::getDefaultCountry()->getId())
            ->setTaxId($this->factory->tax(['requirements' => ['percent' => '20']])->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        return $taxRule;
    }
}
