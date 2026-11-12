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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Map\ProductPriceTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\TaxRule;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Integration\Domain\Pricing\PriceList\PriceListFixtures;
use Thelia\Tests\Integration\Domain\Pricing\PriceVisibility\PriceDisplayFixtures;

/**
 * The front API serves the prices a caller is shown, and null for the others: every price of the
 * sale element, the promotional one and the taxed ones included, whichever way it is read (the
 * product, the sale element, the single price), and whichever way it is asked for (an order on the
 * price, a range on it). It also tells the mode, whether the sale element may be bought and the
 * tiers of the price list that prices it.
 *
 * The shop of the scenario, default currency EUR:
 *
 *   R30 30, R10 10, R20 20 (untaxed catalog prices)
 *
 *   shop visible       everybody reads 30, 10, 20
 *   shop logged_in     a visitor reads null; a customer 30, 10, 20
 *   product on_request nobody reads its price
 */
final class PriceDisplayApiTest extends ApiTestCase
{
    use PriceDisplayFixtures;
    use PriceListFixtures;
    use RecordsSqlQueries;

    private const MAX_PRODUCTS_ENV = 'THELIA_API_PRODUCT_PRICE_SORT_MAX_PRODUCTS';

    private FixtureFactory $factory;

    private Currency $currency;

    private Category $category;

    private TaxRule $taxRule;

    /** @var array<string, Product> */
    private array $products = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->currency = Currency::getDefaultCurrency();
        $this->category = $this->factory->category();
        $this->taxRule = $this->factory->taxRule();

        foreach (['R30' => 30.0, 'R10' => 10.0, 'R20' => 20.0] as $ref => $price) {
            $this->products[$ref] = $this->factory->product($this->category, $this->taxRule, $this->currency, ['ref' => $ref, 'basePrice' => $price, 'baseQuantity' => 100]);
        }

        $this->forgetPriceDisplayMemos();
    }

    protected function tearDown(): void
    {
        putenv(self::MAX_PRODUCTS_ENV);
        unset($_ENV[self::MAX_PRODUCTS_ENV], $_SERVER[self::MAX_PRODUCTS_ENV]);
        $this->forgetPriceDisplayMemos();

        parent::tearDown();
    }

    public function testAShopThatShowsEveryPriceServesThePricesTheModeAndNoTier(): void
    {
        $saleElement = $this->saleElementOf('R30');

        self::assertSame('visible', $saleElement['priceDisplayMode']);
        self::assertTrue($saleElement['purchasable']);
        self::assertSame([], $saleElement['priceTiers']);
        self::assertSame(30.0, (float) $saleElement['productPrices'][0]['price']);
        self::assertNotNull($saleElement['productPrices'][0]['taxedPrice']);
    }

    public function testAVisitorReadsNullPricesWhereTheShopShowsThemToCustomersOnly(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);

        $saleElement = $this->saleElementOf('R30');

        self::assertSame('logged_in', $saleElement['priceDisplayMode']);
        self::assertFalse($saleElement['purchasable']);
        self::assertSame([], $saleElement['priceTiers']);
        $price = $saleElement['productPrices'][0];

        foreach (['price', 'promoPrice', 'taxedPrice', 'taxedPromoPrice'] as $field) {
            // The serializer leaves a null out of the document: a field that is not there reads null.
            self::assertNull($price[$field] ?? null, $field.' is hidden from a visitor');
        }
    }

    public function testACustomerReadsThePricesWhereTheShopShowsThemToCustomersOnly(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);

        $saleElement = $this->saleElementOf('R30', $this->customerToken());

        self::assertSame('logged_in', $saleElement['priceDisplayMode']);
        self::assertTrue($saleElement['purchasable']);
        self::assertSame(30.0, (float) $saleElement['productPrices'][0]['price']);
        self::assertNotNull($saleElement['productPrices'][0]['taxedPrice']);
    }

    /**
     * A visitor shown no price must not learn that the product is on promotion either: the flag
     * the theme strikes the price through with goes with the price.
     */
    public function testAVisitorIsNotToldThatAHiddenPriceIsOnPromotion(): void
    {
        $pse = $this->products['R30']->getDefaultSaleElements();
        $pse->setPromo(1)->save($this->getPropelConnection());
        ProductPriceQuery::create()->filterByProductSaleElementsId($pse->getId())->update(['PromoPrice' => '25.000000']);
        ProductPriceTableMap::clearInstancePool();
        $this->shopShows(PriceDisplayMode::LoggedIn);

        $visitor = $this->saleElementOf('R30');
        $customer = $this->saleElementOf('R30', $this->customerToken());

        self::assertFalse($visitor['promo'] ?? false, 'the flag is the one of a price the visitor is not shown');
        self::assertNull($visitor['displayInitialPrice'] ?? null);
        self::assertTrue($customer['promo'], 'the customer is shown the promotion');
    }

    public function testNobodyReadsThePriceOfAProductOnRequest(): void
    {
        $this->productShows($this->products['R30'], PriceDisplayMode::OnRequest);
        $token = $this->customerToken();

        foreach ([null, $token] as $who) {
            $saleElement = $this->saleElementOf('R30', $who);

            self::assertSame('on_request', $saleElement['priceDisplayMode']);
            self::assertFalse($saleElement['purchasable']);
            self::assertNull($saleElement['productPrices'][0]['price'] ?? null);
            self::assertNull($saleElement['productPrices'][0]['taxedPrice'] ?? null);
        }

        self::assertSame(10.0, (float) $this->saleElementOf('R10')['productPrices'][0]['price'], 'the other products keep the shop mode');
    }

    public function testAProductMayShowItsPricesInAShopThatHidesThem(): void
    {
        $this->shopShows(PriceDisplayMode::OnRequest);
        $this->productShows($this->products['R30'], PriceDisplayMode::Visible);

        self::assertSame(30.0, (float) $this->saleElementOf('R30')['productPrices'][0]['price']);
        self::assertNull($this->saleElementOf('R10')['productPrices'][0]['price'] ?? null);
    }

    public function testASaleElementReadOnItsOwnAndASinglePriceAreHiddenToo(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $saleElementId = (int) $this->products['R30']->getDefaultSaleElements()->getId();

        $saleElement = $this->read('/api/front/product_sale_elements/'.$saleElementId);
        self::assertSame('logged_in', $saleElement['priceDisplayMode']);
        self::assertNull($saleElement['productPrices'][0]['price'] ?? null);

        $single = $this->read(\sprintf('/api/front/product_prices/%d/currencies/%d', $saleElementId, $this->currency->getId()));
        self::assertNull($single['price'] ?? null);
        self::assertNull($single['promoPrice'] ?? null);

        $single = $this->read(\sprintf('/api/front/product_prices/%d/currencies/%d', $saleElementId, $this->currency->getId()), $this->customerToken());
        self::assertSame(30.0, (float) $single['price']);
    }

    public function testTheAdminReadKeepsTheCatalogPrices(): void
    {
        $this->shopShows(PriceDisplayMode::OnRequest);

        $payload = $this->read('/api/admin/products/'.$this->products['R30']->getId(), $this->authenticateAsAdmin());

        self::assertSame(30.0, (float) $payload['productSaleElements'][0]['productPrices'][0]['price']);
        self::assertArrayNotHasKey('priceDisplayMode', $payload['productSaleElements'][0]);
    }

    public function testAnOrderOnThePriceDoesNotTellAVisitorWhereAHiddenPriceStands(): void
    {
        $token = $this->customerToken();

        self::assertSame(['R10', 'R20', 'R30'], $this->refsOf('untaxed_price_order=asc'));

        $this->shopShows(PriceDisplayMode::LoggedIn);

        // All three products have no price to the visitor: they keep the order of the listing.
        self::assertSame(['R30', 'R10', 'R20'], $this->refsOf('untaxed_price_order=asc'));
        self::assertSame(['R30', 'R10', 'R20'], $this->refsOf('untaxed_price_order=desc'));
        self::assertSame(['R10', 'R20', 'R30'], $this->refsOf('untaxed_price_order=asc', $token));
        self::assertSame(['R30', 'R20', 'R10'], $this->refsOf('untaxed_price_order=desc', $token));
    }

    public function testPastTheCeilingTheOrderOnThePublicPricesIsNotAppliedWhileAPriceMayBeHidden(): void
    {
        putenv(self::MAX_PRODUCTS_ENV.'=2');
        $_ENV[self::MAX_PRODUCTS_ENV] = $_SERVER[self::MAX_PRODUCTS_ENV] = '2';

        // Three products, two at most priced one by one: nothing hidden, the database sorts on the public prices.
        self::assertSame(['R10', 'R20', 'R30'], $this->refsOf('untaxed_price_order=asc'));

        $this->productShows($this->products['R10'], PriceDisplayMode::OnRequest);

        // A price may be hidden: the public prices do not order the listing, whose order tells nothing.
        self::assertSame(['R30', 'R10', 'R20'], $this->refsOf('untaxed_price_order=asc'));
        self::assertSame(['R30', 'R10', 'R20'], $this->refsOf('untaxed_price_order=desc'));
    }

    public function testAProductWithAHiddenPriceComesLastInBothDirections(): void
    {
        $this->productShows($this->products['R10'], PriceDisplayMode::OnRequest);

        self::assertSame(['R20', 'R30', 'R10'], $this->refsOf('untaxed_price_order=asc'));
        self::assertSame(['R30', 'R20', 'R10'], $this->refsOf('untaxed_price_order=desc'));
    }

    public function testARangeOnThePriceDoesNotLetAVisitorBracketAHiddenPrice(): void
    {
        $token = $this->customerToken();
        $range = 'productSaleElements.productPrices.price[gt]=15';

        self::assertSame(['R30', 'R20'], $this->refsOf($range));

        $this->shopShows(PriceDisplayMode::LoggedIn);

        self::assertSame([], $this->refsOf($range), 'a visitor learns nothing from an empty range');
        self::assertSame([], $this->refsOf('productSaleElements.productPrices.price[lt]=15'), 'nor from the opposite one');
        self::assertSame(['R30', 'R20'], $this->refsOf($range, $token));
    }

    public function testARangeOnThePromotionalPriceIsKeptFromAVisitorToo(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);

        self::assertSame([], $this->refsOf('productSaleElements.productPrices.promoPrice[gt]=15'));
        self::assertSame(['R30', 'R20'], $this->refsOf('productSaleElements.productPrices.promoPrice[gt]=15', $this->customerToken()));
    }

    /**
     * The promotion of a hidden price reads false on the item: a filter on the flag must answer
     * as if the product were not on promotion, whichever value it is asked for.
     */
    public function testTheFiltersOnThePromotionDoNotTellAVisitorWhichHiddenPriceIsOnPromotion(): void
    {
        $this->promote('R30');
        $token = $this->customerToken();

        self::assertSame(['R30'], $this->refsOf('productSaleElements.promo=true'));
        self::assertSame(['R10', 'R20'], $this->refsOf('productSaleElements.promo=false'));

        $this->shopShows(PriceDisplayMode::LoggedIn);

        self::assertSame([], $this->refsOf('productSaleElements.promo=true'), 'a visitor is told of no promotion');
        self::assertSame(['R30', 'R10', 'R20'], $this->refsOf('productSaleElements.promo=false'), 'every product reads promo=false to a visitor');
        self::assertSame(['R30'], $this->refsOf('productSaleElements.promo=true', $token));
        self::assertSame(['R10', 'R20'], $this->refsOf('productSaleElements.promo=false', $token));
    }

    public function testTheFilterOnThePromotionOfSaleElementsDoesNotTellAVisitorWhichHiddenPriceIsOnPromotion(): void
    {
        $this->promote('R30');
        $token = $this->customerToken();

        self::assertSame(['R30'], $this->refsOfSaleElements('promo=true'));
        self::assertSame(['R10', 'R20'], $this->refsOfSaleElements('promo=false'));

        $this->shopShows(PriceDisplayMode::LoggedIn);

        self::assertSame([], $this->refsOfSaleElements('promo=true'), 'no sale element is promoted to a visitor');
        self::assertSame(['R30', 'R10', 'R20'], $this->refsOfSaleElements('promo=false'), 'every one reads promo=false to a visitor');
        self::assertSame(['R30'], $this->refsOfSaleElements('promo=true', $token));
        self::assertSame(['R10', 'R20'], $this->refsOfSaleElements('promo=false', $token));
    }

    public function testTheFilterOnThePromotionAddsNothingToTheQueryWhileEveryPriceIsShown(): void
    {
        $this->promote('R30');
        $statements = $this->recordSqlQueries(function (): void {
            self::assertSame(['R30'], $this->refsOf('productSaleElements.promo=true'));
        });

        self::assertSame([], array_filter($statements, static fn (string $statement): bool => str_contains($statement, 'price_display_product')), 'no condition on the mode of the products');
    }

    public function testAPriceListOfTheGroupOfTheCustomerOrdersTheProductsOnTheListPrice(): void
    {
        $group = $this->factory->customerGroup();
        $list = $this->priceList('PRO');
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $this->products['R30']->getDefaultSaleElements(), '5');
        $this->forgetPriceDisplayMemos();
        $token = $this->customerToken($group);

        self::assertSame(['R10', 'R20', 'R30'], $this->refsOf('untaxed_price_order=asc'), 'the visitor is not of the group');
        self::assertSame(['R30', 'R10', 'R20'], $this->refsOf('untaxed_price_order=asc', $token));
        self::assertSame(['R20', 'R10', 'R30'], $this->refsOf('untaxed_price_order=desc', $token));
    }

    public function testTheTiersOfThePriceListOfTheCustomerAreServedWithTheirTaxes(): void
    {
        $pse = $this->products['R30']->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $list = $this->priceList('PRO');
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, '9', 10);
        $this->priceListItem($list, $pse, '10', 1);
        $this->forgetPriceDisplayMemos();
        $token = $this->customerToken($group);

        $tiers = $this->saleElementOf('R30', $token)['priceTiers'];

        self::assertCount(2, $tiers);
        self::assertSame([1, 10.0, 10.0], [$tiers[0]['minQuantity'], (float) $tiers[0]['price'], (float) $tiers[0]['promoPrice']]);
        self::assertSame([10, 9.0, 9.0], [$tiers[1]['minQuantity'], (float) $tiers[1]['price'], (float) $tiers[1]['promoPrice']]);
        self::assertArrayHasKey('taxedPrice', $tiers[1]);
        self::assertGreaterThanOrEqual(9.0, (float) $tiers[1]['taxedPrice']);
        self::assertSame([], $this->saleElementOf('R30')['priceTiers'], 'a visitor is not assigned the list');
    }

    public function testTheTiersAreNotServedToACallerWhoIsNotShownThePrices(): void
    {
        $pse = $this->products['R30']->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $list = $this->priceList('PRO');
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, '9', 10);
        $this->productShows($this->products['R30'], PriceDisplayMode::OnRequest);
        $token = $this->customerToken($group);

        self::assertSame([], $this->saleElementOf('R30', $token)['priceTiers']);
        self::assertFalse($this->saleElementOf('R30', $token)['purchasable']);
    }

    public function testASaleElementAnExclusiveListDoesNotPriceIsShownButNotPurchasable(): void
    {
        $product = $this->products['R30'];
        $other = $this->factory->productSaleElement($product);
        $this->factory->productPrice($other, $this->currency, ['price' => '25.000000', 'promoPrice' => '25.000000']);
        $group = $this->factory->customerGroup();
        $list = $this->priceList('EXCL', exclusive: true);
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $other, '20');
        $this->forgetPriceDisplayMemos();

        $payload = $this->read('/api/front/products/'.$product->getId(), $this->customerToken($group));
        $byId = [];

        foreach ($payload['productSaleElements'] as $saleElement) {
            $byId[(int) $saleElement['id']] = $saleElement;
        }

        self::assertFalse($byId[(int) $product->getDefaultSaleElements()->getId()]['purchasable']);
        self::assertTrue($byId[(int) $other->getId()]['purchasable']);
        self::assertSame(20.0, (float) $byId[(int) $other->getId()]['productPrices'][0]['price']);
    }

    /**
     * A listing reads the modes of its page in one statement, and the tiers of the price lists of its
     * page in one: neither grows with the page.
     */
    public function testAPageOfProductsReadsTheModesAndTheTiersOnce(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $group = $this->factory->customerGroup();
        $list = $this->priceList('PRO');
        $this->assignToGroup($list, $group);

        foreach ($this->products as $product) {
            $this->priceListItem($list, $product->getDefaultSaleElements(), '5', 10);
        }

        $this->forgetPriceDisplayMemos();
        $token = $this->customerToken($group);
        $payload = [];

        $statements = $this->recordSqlQueries(function () use (&$payload, $token): void {
            $payload = $this->read('/api/front/products?'.implode('&', array_map(static fn (Product $product): string => 'id[]='.$product->getId(), $this->products)), $token);
        });

        self::assertCount(3, $payload['hydra:member']);

        foreach ($payload['hydra:member'] as $member) {
            self::assertSame([['minQuantity' => 10, 'price' => 5.0]], array_map(static fn (array $tier): array => ['minQuantity' => $tier['minQuantity'], 'price' => (float) $tier['price']], $member['productSaleElements'][0]['priceTiers']));
        }

        self::assertSame(1, self::countSqlQueriesSelectingFrom($statements, 'price_list_item'), 'one read of the lines for the page');
        self::assertSame(1, \count(array_filter($statements, static fn (string $statement): bool => str_contains($statement, 'AS "PriceDisplayMode"'))), 'one read of the modes for the page');
    }

    private function promote(string $ref): void
    {
        $pse = $this->products[$ref]->getDefaultSaleElements();
        $pse->setPromo(1)->save($this->getPropelConnection());
        ProductPriceQuery::create()->filterByProductSaleElementsId($pse->getId())->update(['PromoPrice' => '5.000000']);
        ProductPriceTableMap::clearInstancePool();
    }

    /**
     * @return array<string, mixed>
     */
    private function saleElementOf(string $ref, ?string $token = null): array
    {
        $payload = $this->read('/api/front/products/'.$this->products[$ref]->getId(), $token);

        return $payload['productSaleElements'][0];
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $uri, ?string $token = null): array
    {
        $response = $this->jsonRequest('GET', $uri, token: $token);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string>
     */
    private function refsOf(string $query, ?string $token = null): array
    {
        $ids = implode('&', array_map(static fn (Product $product): string => 'id[]='.$product->getId(), $this->products));
        $payload = $this->read('/api/front/products?'.$ids.'&'.$query, $token);

        return array_column($payload['hydra:member'], 'ref');
    }

    /**
     * The products whose default sale element the filter keeps, one read per product: the
     * collection of sale elements cannot be narrowed to a few products in one.
     *
     * @return list<string>
     */
    private function refsOfSaleElements(string $query, ?string $token = null): array
    {
        $refs = [];

        foreach ($this->products as $ref => $product) {
            $payload = $this->read('/api/front/product_sale_elements?isDefault=true&product.id='.$product->getId().'&'.$query, $token);

            if ([] !== $payload['hydra:member']) {
                $refs[] = $ref;
            }
        }

        return $refs;
    }

    private function customerToken(?\Thelia\Model\CustomerGroup $group = null): string
    {
        $customer = $this->factory->customer($this->factory->customerTitle(), ['password' => 'password'] + (null === $group ? [] : ['customerGroup' => $group]));

        return $this->authenticateAsCustomer($customer);
    }
}
