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

namespace Thelia\Tests\Api\Front;

use Thelia\Api\Service\DataAccess\ProductSaleElementsAccessService;
use Thelia\Model\Category;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The cards of a product list read the taxed price of the API instead of taxing the untaxed
 * one themselves: the figure the product loop computes, the one the product page prints, for
 * the visitor who asks, and a page of products costs no more statements for taxing.
 *
 * Two tax rules on the default country: 20 % and 5.5 %.
 */
final class ProductTaxedPriceApiTest extends ApiTestCase
{
    use RecordsSqlQueries;

    private Currency $currency;

    private Category $category;

    private TaxRule $standardRule;

    private TaxRule $reducedRule;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->currency = Currency::getDefaultCurrency();
        $this->category = $factory->category();
        $this->standardRule = $this->taxRuleAt('20.000000');
        $this->reducedRule = $this->taxRuleAt('5.500000');
    }

    public function testTheCardCarriesTheTaxedPricesTheProductLoopComputes(): void
    {
        $standard = $this->product($this->standardRule, 100.0);
        $reduced = $this->product($this->reducedRule, 49.99, 39.99);

        $country = Country::getDefaultCountry();

        foreach ([$standard, $reduced] as $product) {
            $price = $this->listed($product)['productSaleElements'][0]['productPrices'][0];

            // What the product loop reads for TAXED_PRICE and TAXED_PROMO_PRICE.
            self::assertEqualsWithDelta($product->getTaxedPrice($country, $price['price']), $price['taxedPrice'], 1e-9, $product->getRef());
            self::assertEqualsWithDelta($product->getTaxedPromoPrice($country, $price['promoPrice']), $price['taxedPromoPrice'], 1e-9, $product->getRef());
        }

        $price = $this->listed($standard)['productSaleElements'][0]['productPrices'][0];
        self::assertEqualsWithDelta(120.0, $price['taxedPrice'], 1e-9, '100 at 20 %.');

        $price = $this->listed($reduced)['productSaleElements'][0]['productPrices'][0];
        self::assertEqualsWithDelta(49.99 * 1.055, $price['taxedPrice'], 1e-9, '49.99 at 5.5 %.');
        self::assertEqualsWithDelta(39.99 * 1.055, $price['taxedPromoPrice'], 1e-9, 'The promotional price is taxed at the same rate.');
    }

    /**
     * The card and the page it opens are served by one source: what the page publishes as the
     * taxed price of its sale elements is what the card published as `taxedPrice`.
     */
    public function testTheCardAndThePageOfAProductPrintTheSameTaxedPrice(): void
    {
        $product = $this->product($this->reducedRule, 49.99, 39.99);

        $card = $this->listed($product)['productSaleElements'][0]['productPrices'][0];
        $page = json_decode((string) $this->getService(ProductSaleElementsAccessService::class)->psesByProduct($product->getId()), true, flags: \JSON_THROW_ON_ERROR)[0];

        self::assertEqualsWithDelta($card['taxedPrice'], $page['price'], 1e-9);
        self::assertEqualsWithDelta($card['taxedPromoPrice'], $page['promoPrice'], 1e-9);
        self::assertEqualsWithDelta(49.99, $page['untaxedPrice'], 1e-9);

        $single = $this->readJson('/api/front/products/'.$product->getId())['productSaleElements'][0]['productPrices'][0];
        self::assertEqualsWithDelta($card['taxedPrice'], $single['taxedPrice'], 1e-9, 'The single read of the product says the same.');
    }

    public function testASaleElementReadOnItsOwnCarriesTheTaxedPriceToo(): void
    {
        $product = $this->product($this->standardRule, 100.0);

        $payload = $this->readJson('/api/front/product_sale_elements/'.$product->getDefaultSaleElements()->getId());

        self::assertEqualsWithDelta(120.0, $payload['productPrices'][0]['taxedPrice'], 1e-9);
    }

    public function testTheBackOfficeReadsNoTaxedPrice(): void
    {
        $product = $this->product($this->standardRule, 100.0);

        $payload = $this->readJson('/api/admin/products/'.$product->getId(), $this->authenticateAsAdmin());

        self::assertArrayNotHasKey('taxedPrice', $payload['productSaleElements'][0]['productPrices'][0]);
    }

    /**
     * Two customers and a visitor, in a row: each is served the taxed price of the price they
     * pay, never the one of another.
     */
    public function testTwoCustomersAndAVisitorAreServedTheTaxedPriceOfTheirOwnPrice(): void
    {
        $product = $this->product($this->standardRule, 100.0);
        $first = $this->member(20.0);
        $second = $this->member(50.0, 10.0);
        $firstToken = $this->authenticateAsCustomer($first);
        $secondToken = $this->authenticateAsCustomer($second);

        $taxedFor = fn (?string $token): float => (float) $this->listed($product, $token)['productSaleElements'][0]['productPrices'][0]['taxedPrice'];

        self::assertEqualsWithDelta(96.0, $taxedFor($firstToken), 1e-9, '100 less 20 %, taxed at 20 %.');
        self::assertEqualsWithDelta(54.0, $taxedFor($secondToken), 1e-9, '100 less 50 % less 10 %, taxed at 20 %.');
        self::assertEqualsWithDelta(120.0, $taxedFor(null), 1e-9);
        self::assertEqualsWithDelta(96.0, $taxedFor($firstToken), 1e-9);
    }

    /**
     * A page of thirty is taxed by the statements of a page of three: the products come with
     * their tax rule in one read, and the rates of a rule are read once.
     */
    public function testAPageOfProductsIsTaxedForTheStatementsOfASmallOne(): void
    {
        $products = [];
        foreach (range(1, 30) as $i) {
            $products[] = $this->product(0 === $i % 2 ? $this->standardRule : $this->reducedRule, 10.0 + $i);
        }

        $statementsFor = function (int $size) use ($products): array {
            $ids = implode('&id[]=', array_map(static fn (Product $product): int => $product->getId(), \array_slice($products, 0, $size)));

            return $this->recordSqlQueries(function () use ($ids, $size): void {
                $members = $this->readJson('/api/front/products?itemsPerPage=50&id[]='.$ids)['hydra:member'];
                self::assertCount($size, $members);

                foreach ($members as $member) {
                    self::assertNotNull($member['productSaleElements'][0]['productPrices'][0]['taxedPrice']);
                }
            });
        };

        // The rates of a rule are kept by the tax rule query for the process: a first read of
        // the page pays for them, so that what is measured next is what a product costs.
        $statementsFor(30);

        $small = $statementsFor(4);
        $large = $statementsFor(30);

        foreach (['tax_rule', 'tax_rule_country', 'tax', 'product'] as $table) {
            self::assertSame(
                self::countSqlQueriesSelectingFrom($small, $table),
                self::countSqlQueriesSelectingFrom($large, $table),
                'The reads of "'.$table.'" must not grow with the page.',
            );
        }

        self::assertSame(
            \count($small),
            \count($large),
            'A page of thirty products costs the statements of a page of four, taxed prices included.',
        );
    }

    private function taxRuleAt(string $percent): TaxRule
    {
        $factory = $this->createFixtureFactory();
        $taxRule = $factory->taxRule(['isDefault' => false]);
        $tax = $factory->tax(['requirements' => ['percent' => $percent], 'title' => 'VAT '.$percent]);

        (new TaxRuleCountry())
            ->setTaxRuleId($taxRule->getId())
            ->setCountryId(Country::getDefaultCountry()->getId())
            ->setTaxId($tax->getId())
            ->setPosition(1)
            ->save($this->getPropelConnection());

        return $taxRule;
    }

    private function product(TaxRule $taxRule, float $price, ?float $promoPrice = null): Product
    {
        $product = $this->createFixtureFactory()->product(
            $this->category,
            $taxRule,
            $this->currency,
            ['baseQuantity' => 100, 'basePrice' => $price, 'title' => 'Taxed price product'],
        );

        if (null !== $promoPrice) {
            ProductPriceQuery::create()
                ->filterByProductSaleElementsId($product->getDefaultSaleElements()->getId())
                ->update(['PromoPrice' => (string) $promoPrice], null, true);
        }

        return $product;
    }

    private function member(float $groupDiscount, float $customerDiscount = 0.0): Customer
    {
        $factory = $this->createFixtureFactory();
        $member = $factory->customer($factory->customerTitle(), ['customerGroup' => $factory->customerGroup(discount: $groupDiscount), 'password' => 'password']);
        $member->setDiscount((string) $customerDiscount)->save($this->getPropelConnection());

        return $member;
    }

    /**
     * The product as the list of the front API gives it.
     *
     * @return array<string, mixed>
     */
    private function listed(Product $product, ?string $token = null): array
    {
        return $this->readJson('/api/front/products?id[]='.$product->getId(), $token)['hydra:member'][0];
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $uri, ?string $token = null): array
    {
        $response = $this->jsonRequest('GET', $uri, token: $token);
        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
