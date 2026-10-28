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

use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\Sale;
use Thelia\Model\TaxRule;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * "untaxed_price_order" sorts the collection on the untaxed price the visitor pays for the
 * default combination, in the currency browsed.
 *
 * Where nothing prices the catalog for who is asking, the database sorts on the public price
 * (the promotional price and the rule open to everyone included). Where a group, a discount,
 * a reserved operation or a rule may, the narrowed set is priced by the resolver of the cards
 * and sorted in PHP.
 *
 * The shop of the scenario, default currency EUR:
 *
 *   A  85 catalog
 *   B  90 catalog, 80 promotional
 *   C 120 catalog, 60 for the group G
 *   G  15 % off, untaxed prices; M belongs to it and has a 10 % discount of their own
 *
 *   visitor  A 85    B 80    C 120    -> B, A, C
 *   M        A 65.03 B 68.85 C 54     -> C, A, B   (before M's discount: 60, 72.25, 76.50)
 */
final class ProductPriceOrderApiTest extends ApiTestCase
{
    use RecordsSqlQueries;

    private const MAX_PRODUCTS_ENV = 'THELIA_API_PRODUCT_PRICE_SORT_MAX_PRODUCTS';

    private Currency $currency;

    private Customer $member;

    private Category $category;

    private TaxRule $taxRule;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->currency = Currency::getDefaultCurrency();
        $this->category = $factory->category();
        $this->taxRule = $factory->taxRule();
    }

    protected function tearDown(): void
    {
        putenv(self::MAX_PRODUCTS_ENV);
        unset($_ENV[self::MAX_PRODUCTS_ENV], $_SERVER[self::MAX_PRODUCTS_ENV]);

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // The database sorts: nothing prices the catalog for the visitor
    // ------------------------------------------------------------------

    public function testTheSortReadsTheDefaultCombinationOnly(): void
    {
        $dear = $this->product('DEAR', 50.0);
        $this->product('CHEAP', 20.0);
        // A cheaper combination that is not the default one does not move the product.
        $factory = $this->createFixtureFactory();
        $factory->productPrice($factory->productSaleElement($dear), $this->currency, ['price' => '5.000000', 'promoPrice' => '5.000000']);

        self::assertSame(['CHEAP', 'DEAR'], $this->refsOf('untaxed_price_order=asc'));
        self::assertSame(['DEAR', 'CHEAP'], $this->refsOf('untaxed_price_order=desc'));
        self::assertSame(2, $this->totalOf('untaxed_price_order=asc'), 'One row per product.');
    }

    public function testTheSortReadsThePriceOfTheCurrencyBrowsed(): void
    {
        $quid = $this->createFixtureFactory()->currency(['code' => 'QQQ', 'symbol' => 'Q', 'rate' => 2.0, 'byDefault' => 0]);
        $typed = $this->product('TYPED', 50.0);
        $this->product('CONVERTED', 20.0);
        // 50 in the default currency, but typed 10 in the other one: cheaper there.
        $this->createFixtureFactory()->productPrice($typed->getDefaultSaleElements(), $quid, ['price' => '10.000000', 'promoPrice' => '10.000000']);

        self::assertSame(['CONVERTED', 'TYPED'], $this->refsOf('untaxed_price_order=asc'));
        // CONVERTED is 20 in the default currency, converted at the rate of the other one.
        self::assertSame(['TYPED', 'CONVERTED'], $this->refsOf('untaxed_price_order=asc&currency=QQQ'));
    }

    public function testThePromotionalPriceIsTheOneSorted(): void
    {
        $this->product('A', 85.0);
        $this->product('B', 90.0, promoPrice: 80.0);
        $this->product('C', 120.0);

        self::assertSame(['B', 'A', 'C'], $this->refsOf('untaxed_price_order=asc'));
        self::assertSame(['C', 'A', 'B'], $this->refsOf('untaxed_price_order=desc'));
    }

    public function testThePromotionalPriceIsConvertedInASecondaryCurrency(): void
    {
        $this->createFixtureFactory()->currency(['code' => 'QQQ', 'symbol' => 'Q', 'rate' => 2.0, 'byDefault' => 0]);
        $this->product('A', 85.0);
        $this->product('B', 90.0, promoPrice: 80.0);
        $this->product('C', 120.0);

        // 170, 160, 240 in the other currency: the promotion of B still ranks it first.
        self::assertSame(['B', 'A', 'C'], $this->refsOf('untaxed_price_order=asc&currency=QQQ'));
        self::assertSame(['C', 'A', 'B'], $this->refsOf('untaxed_price_order=desc&currency=QQQ'));
    }

    public function testARuleOpenToEveryoneIsSortedOnToo(): void
    {
        $a = $this->product('A', 85.0);
        $this->product('B', 90.0, promoPrice: 80.0);
        $this->product('C', 120.0);
        $this->ruleOn([$a], 50.0);

        // A costs 42.50 under the rule: the visitor reads it on the card, the sort follows.
        self::assertSame(['A', 'B', 'C'], $this->refsOf('untaxed_price_order=asc'));
        self::assertSame(['C', 'B', 'A'], $this->refsOf('untaxed_price_order=desc'));
    }

    public function testARuleNotYetInForceIsNotSortedOn(): void
    {
        $a = $this->product('A', 85.0);
        $this->product('B', 90.0, promoPrice: 80.0);
        // Its segment is stored, from tomorrow on: it does not price anything today.
        $this->ruleOn([$a], 50.0, ['startDate' => new \DateTime('+1 day')]);

        self::assertSame(['B', 'A'], $this->refsOf('untaxed_price_order=asc'));
    }

    // ------------------------------------------------------------------
    // The resolver prices the narrowed set: a group, a discount, a rule... may price
    // ------------------------------------------------------------------

    public function testAVisitorIsSortedOnThePromotionalPrice(): void
    {
        $this->scenario();

        self::assertSame(['B', 'A', 'C'], $this->refsOf('untaxed_price_order=asc'));
        self::assertSame(['C', 'A', 'B'], $this->refsOf('untaxed_price_order=desc'));
    }

    public function testAVisitorOutsideTheGroupThatPricesIsSortedByTheDatabase(): void
    {
        $this->scenario();

        $statements = $this->recordSqlQueries(fn () => self::assertSame(['B', 'A', 'C'], $this->refsOf('untaxed_price_order=asc')));

        self::assertNotSame([], $this->statementsContaining($statements, 'product_price_order_key'), 'The database ranks...');
        self::assertSame([], $this->statementsContaining($statements, 'FIELD('), '...and nothing is resolved in PHP: the group G prices for its members only.');
    }

    public function testAMemberOfTheGroupThatPricesIsSortedInPhp(): void
    {
        $token = $this->scenario();

        $statements = $this->recordSqlQueries(fn () => self::assertSame(['C', 'A', 'B'], $this->refsOf('untaxed_price_order=asc', $token)));

        self::assertNotSame([], $this->statementsContaining($statements, 'FIELD('));
    }

    public function testADiscountOfTheCustomerAloneKeepsTheDatabaseOrder(): void
    {
        $this->scenario();
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $customer->setDiscount('10')->save();
        $token = $this->authenticateAsCustomer($customer);

        $statements = $this->recordSqlQueries(fn () => self::assertSame(['B', 'A', 'C'], $this->refsOf('untaxed_price_order=asc', $token)));

        self::assertSame([], $this->statementsContaining($statements, 'FIELD('), 'A rate common to every price moves no product past another.');
        self::assertSame(['C', 'A', 'B'], $this->refsOf('untaxed_price_order=desc', $token));
    }

    public function testAMemberIsSortedOnWhatTheyPay(): void
    {
        $token = $this->scenario();

        self::assertSame(['C', 'A', 'B'], $this->refsOf('untaxed_price_order=asc', $token));
        self::assertSame(['B', 'A', 'C'], $this->refsOf('untaxed_price_order=desc', $token));
        self::assertSame(3, $this->totalOf('untaxed_price_order=asc', $token));
    }

    public function testARuleOpenToEveryoneMovesTheOrderOfTheVisitorAndOfTheMember(): void
    {
        $token = $this->scenario();
        $this->ruleOn([$this->productByRef('A')], 50.0);

        self::assertSame(['A', 'B', 'C'], $this->refsOf('untaxed_price_order=asc'));
        // A is 42.50 under the rule, and still dearer than... nothing the group gives: it is the cheapest of the member too.
        self::assertSame(['A', 'C', 'B'], $this->refsOf('untaxed_price_order=asc', $token));
    }

    public function testAReservedOperationNamingTheMemberOrdersForThemOnly(): void
    {
        $token = $this->scenario();
        $factory = $this->createFixtureFactory();
        $sale = $factory->sale([
            'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMERS,
            'active' => true,
            'startDate' => new \DateTime('-1 day'),
            'endDate' => new \DateTime('+1 day'),
        ]);
        $factory->saleProduct($sale, $this->productByRef('B'));
        $factory->saleCustomer($sale, $this->member);
        $factory->saleOffsetCurrency($sale, $this->currency, 50.0);

        // B is 45 for M, under the 54 of C.
        self::assertSame(['B', 'C', 'A'], $this->refsOf('untaxed_price_order=asc', $token));
        self::assertSame(['B', 'A', 'C'], $this->refsOf('untaxed_price_order=asc'), 'The visitor is not named: nothing moves.');
    }

    public function testASecondaryCurrencyWithoutATypedPriceKeepsTheOrder(): void
    {
        $token = $this->scenario();
        $this->createFixtureFactory()->currency(['code' => 'QQQ', 'symbol' => 'Q', 'rate' => 2.0, 'byDefault' => 0]);

        self::assertSame(['B', 'A', 'C'], $this->refsOf('untaxed_price_order=asc&currency=QQQ'));
        self::assertSame(['C', 'A', 'B'], $this->refsOf('untaxed_price_order=asc&currency=QQQ', $token));
    }

    public function testAProductWithoutAPriceComesLastWhicheverTheDirection(): void
    {
        $token = $this->scenario();
        $this->product('NONE', 10.0);
        ProductPriceQuery::create()->filterByProductSaleElementsId($this->productByRef('NONE')->getDefaultSaleElements()->getId())->delete();

        self::assertSame(['B', 'A', 'C', 'NONE'], $this->refsOf('untaxed_price_order=asc'));
        self::assertSame(['C', 'A', 'B', 'NONE'], $this->refsOf('untaxed_price_order=desc'));
        self::assertSame(['C', 'A', 'B', 'NONE'], $this->refsOf('untaxed_price_order=asc', $token));
        self::assertSame(['B', 'A', 'C', 'NONE'], $this->refsOf('untaxed_price_order=desc', $token));
        self::assertSame(4, $this->totalOf('untaxed_price_order=asc', $token));
    }

    public function testEveryPageFollowsTheOrderOfTheWholeSet(): void
    {
        $token = $this->scenario();

        $page2 = $this->decode('untaxed_price_order=asc&itemsPerPage=1&page=2', $token);

        self::assertSame(['A'], array_column($page2['hydra:member'], 'ref'));
        self::assertSame(3, $page2['hydra:totalItems']);
        self::assertSame(['B'], $this->refsOf('untaxed_price_order=asc&itemsPerPage=1&page=3', $token));
        self::assertSame(['C'], $this->refsOf('untaxed_price_order=asc&itemsPerPage=1&page=1', $token));
        self::assertSame(['B', 'A'], $this->refsOf('untaxed_price_order=asc&itemsPerPage=2&page=1'));

        $view = $page2['hydra:view'] ?? [];
        self::assertStringContainsString('page=3', (string) ($view['hydra:next'] ?? ''));
        self::assertStringContainsString('page=1', (string) ($view['hydra:previous'] ?? ''));
    }

    public function testAPagePastTheLastOneIsTheLastOneOnBothWays(): void
    {
        $token = $this->scenario();

        // The member is ranked in PHP (C, A, B), the visitor by the database (B, A, C): the page
        // past the end is the last one for both, and says so alike.
        $php = $this->decode('untaxed_price_order=asc&itemsPerPage=2&page=99', $token);
        $sql = $this->decode('untaxed_price_order=asc&itemsPerPage=2&page=99');

        self::assertSame(['B'], array_column($php['hydra:member'], 'ref'));
        self::assertSame(['C'], array_column($sql['hydra:member'], 'ref'));
        self::assertSame($sql['hydra:totalItems'], $php['hydra:totalItems']);
        self::assertSame($sql['hydra:view'], $php['hydra:view']);
        self::assertStringContainsString('page=2', (string) $php['hydra:view']['@id']);
    }

    public function testPastTheCeilingTheCollectionIsSortedOnThePublicPrices(): void
    {
        $token = $this->scenario();
        putenv(self::MAX_PRODUCTS_ENV.'=2');
        $_ENV[self::MAX_PRODUCTS_ENV] = $_SERVER[self::MAX_PRODUCTS_ENV] = '2';

        // Three products, two at most priced one by one: the member reads the public order,
        // the promotion of B included, and the total is the one of the whole collection.
        self::assertSame(['B', 'A', 'C'], $this->refsOf('untaxed_price_order=asc', $token));
        self::assertSame(['C', 'A', 'B'], $this->refsOf('untaxed_price_order=desc', $token));
        self::assertSame(3, $this->totalOf('untaxed_price_order=asc', $token));
        self::assertSame(['A'], $this->refsOf('untaxed_price_order=asc&itemsPerPage=1&page=2', $token));
    }

    public function testTheOtherOrderSettlesTheProductsPricedAlike(): void
    {
        $token = $this->scenario();
        $this->product('A2', 85.0);

        // The price comes first, on both ways, whichever order the parameters are given in.
        self::assertSame(['B', 'A2', 'A', 'C'], $this->refsOf('untaxed_price_order=asc&order[ref]=desc'));
        self::assertSame(['B', 'A', 'A2', 'C'], $this->refsOf('order[ref]=asc&untaxed_price_order=asc'));
        self::assertSame(['C', 'A2', 'A', 'B'], $this->refsOf('untaxed_price_order=asc&order[ref]=desc', $token));
        self::assertSame(['C', 'A', 'A2', 'B'], $this->refsOf('order[ref]=asc&untaxed_price_order=asc', $token));
        self::assertSame(['B', 'A2', 'A', 'C'], $this->refsOf('untaxed_price_order=desc&order[ref]=desc', $token));
    }

    // ------------------------------------------------------------------
    // The price facet, drawn on the same prices
    // ------------------------------------------------------------------

    public function testTheMemberPriceFacetNarrowsTheListingSortedOnTheirPrices(): void
    {
        $token = $this->scenario();

        // The prices M pays: C 54, A 65.03, B 68.85.
        self::assertSame(['C', 'A'], $this->refsOf('untaxed_price_order=asc&tfilters[price][price][max]=66', $token));
        self::assertSame(['A', 'C'], $this->refsOf('untaxed_price_order=desc&tfilters[price][price][max]=66', $token));
        self::assertSame(['B'], $this->refsOf('untaxed_price_order=asc&tfilters[price][price][min]=66', $token));
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    /**
     * @return string the token of M
     */
    private function scenario(): string
    {
        $factory = $this->createFixtureFactory();
        $this->product('A', 85.0);
        $this->product('B', 90.0, promoPrice: 80.0);
        $c = $this->product('C', 120.0);

        $group = $factory->customerGroup(discount: 15.0, displayTaxedPrice: false);
        $factory->customerGroupProductPrice($group, $c->getDefaultSaleElements(), $this->currency, 60.0);
        $this->member = $factory->customer($factory->customerTitle(), ['customerGroup' => $group, 'password' => 'password']);
        $this->member->setDiscount('10')->save();

        return $this->authenticateAsCustomer($this->member);
    }

    private function product(string $ref, float $price, ?float $promoPrice = null): Product
    {
        $product = $this->createFixtureFactory()->product($this->category, $this->taxRule, $this->currency, ['ref' => $ref, 'basePrice' => $price, 'baseQuantity' => 10]);

        if (null !== $promoPrice) {
            $saleElements = $product->getDefaultSaleElements();
            $saleElements->setPromo(1)->save();
            ProductPriceQuery::create()
                ->filterByProductSaleElementsId($saleElements->getId())
                ->filterByCurrencyId($this->currency->getId())
                ->findOne()
                ->setPromoPrice((string) $promoPrice)
                ->save();
        }

        return $product;
    }

    private function productByRef(string $ref): Product
    {
        return ProductQuery::create()->findOneByRef($ref);
    }

    /**
     * @param list<Product> $products
     */
    private function ruleOn(array $products, float $percentage, array $overrides = []): CatalogPriceRule
    {
        $factory = $this->createFixtureFactory();
        $rule = $factory->catalogPriceRule($overrides + ['active' => true, 'percentageValue' => $percentage]);

        foreach ($products as $product) {
            $factory->catalogPriceRuleCriterion($rule, CatalogPriceRule::CRITERION_PRODUCT, $product->getId());
        }

        $this->getService(RuleRepricer::class)->afterRuleChanged($rule);

        return $rule;
    }

    /**
     * @param list<string> $statements
     *
     * @return list<string>
     */
    private function statementsContaining(array $statements, string $needle): array
    {
        return array_values(array_filter($statements, static fn (string $statement): bool => str_contains($statement, $needle)));
    }

    /**
     * @return list<string>
     */
    private function refsOf(string $query, ?string $token = null): array
    {
        return array_column($this->decode($query, $token)['hydra:member'], 'ref');
    }

    private function totalOf(string $query, ?string $token = null): int
    {
        return (int) $this->decode($query, $token)['hydra:totalItems'];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $query, ?string $token = null): array
    {
        $response = $this->jsonRequest('GET', '/api/front/products?'.$query, token: $token);
        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
