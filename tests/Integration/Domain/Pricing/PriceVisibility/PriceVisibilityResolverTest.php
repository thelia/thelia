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

namespace Thelia\Tests\Integration\Domain\Pricing\PriceVisibility;

use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Domain\Pricing\PriceVisibility\PriceVisibilityResolver;
use Thelia\Model\Currency;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Integration\Domain\Pricing\PriceList\PriceListFixtures;

/**
 * The mode of a product is the one it states, else the one of the shop; a visitor and a customer
 * are not shown the same prices; and a shop that hides nothing asks the database for nothing.
 */
final class PriceVisibilityResolverTest extends ActionIntegrationTestCase
{
    use PriceDisplayFixtures;
    use PriceListFixtures;
    use RecordsSqlQueries;

    private PriceVisibilityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->forgetPriceDisplayMemos();
        $this->resolver = $this->getService(PriceVisibilityResolver::class);
    }

    protected function tearDown(): void
    {
        $this->forgetPriceDisplayMemos();

        parent::tearDown();
    }

    /**
     * The example of the contract: shop logged_in, product null, visitor.
     */
    public function testTheShopModeAppliesToAProductThatStatesNone(): void
    {
        $product = $this->pricedProduct();
        $customer = $this->memberOf($this->factory->customerGroup());
        $this->shopShows(PriceDisplayMode::LoggedIn);

        $mode = $this->resolver->modeFor($product, null);

        self::assertSame(PriceDisplayMode::LoggedIn, $mode);
        self::assertFalse($mode->showsPrice(null));
        self::assertFalse($mode->purchasable(null));
        self::assertTrue($this->resolver->modeFor($product, $customer)->showsPrice($customer));
        self::assertTrue($this->resolver->modeFor($product, $customer)->purchasable($customer));
    }

    public function testAProductOnRequestInAShopThatShowsEverythingIsShownToNobody(): void
    {
        $product = $this->pricedProduct();
        $other = $this->pricedProduct();
        $customer = $this->memberOf($this->factory->customerGroup());
        $this->productShows($product, PriceDisplayMode::OnRequest);

        self::assertSame(PriceDisplayMode::OnRequest, $this->resolver->modeFor($product, null));
        self::assertFalse($this->resolver->showsPriceOf((int) $product->getId(), null));
        self::assertFalse($this->resolver->showsPriceOf((int) $product->getId(), $customer));
        self::assertTrue($this->resolver->showsPriceOf((int) $other->getId(), null), 'the other products keep the mode of the shop');
    }

    public function testAProductMayShowItsPricesInAShopThatHidesThem(): void
    {
        $product = $this->pricedProduct();
        $this->shopShows(PriceDisplayMode::OnRequest);
        $this->productShows($product, PriceDisplayMode::Visible);

        self::assertSame(PriceDisplayMode::Visible, $this->resolver->modeFor($product, null));
        self::assertTrue($this->resolver->showsPriceOf((int) $product->getId(), null));
        self::assertSame(PriceDisplayMode::OnRequest, $this->resolver->modeFor($this->pricedProduct(), null));
    }

    public function testAProductHoldingAnUnknownModeFollowsTheShop(): void
    {
        $product = $this->pricedProduct();
        $product->setPriceDisplayMode('sometimes')->save($this->getPropelConnection());
        $this->shopShows(PriceDisplayMode::LoggedIn);

        self::assertSame(PriceDisplayMode::LoggedIn, $this->resolver->modeFor($product, null));
        self::assertSame(PriceDisplayMode::LoggedIn, $this->resolver->modeOfProductId((int) $product->getId()));
    }

    public function testTheProductsWithAHiddenPriceAreFoundInOneStatement(): void
    {
        $products = [$this->pricedProduct(), $this->pricedProduct(), $this->pricedProduct()];
        $customer = $this->memberOf($this->factory->customerGroup());
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $this->productShows($products[1], PriceDisplayMode::Visible);
        $this->productShows($products[2], PriceDisplayMode::OnRequest);
        $ids = array_map(static fn ($product): int => (int) $product->getId(), $products);
        $this->resolver->isActive();

        $statements = $this->recordSqlQueries(function () use ($ids, $customer, &$hiddenFromVisitor, &$hiddenFromCustomer): void {
            $hiddenFromVisitor = $this->resolver->productsWithHiddenPrice($ids, null);
            $hiddenFromCustomer = $this->resolver->productsWithHiddenPrice($ids, $customer);
        });

        self::assertSame([$ids[0], $ids[2]], $hiddenFromVisitor);
        self::assertSame([$ids[2]], $hiddenFromCustomer);
        self::assertCount(1, self::selectingFromProduct($statements));
    }

    public function testAShopThatHidesNothingAnswersWithoutAStatement(): void
    {
        $product = $this->pricedProduct();
        $this->resolver->isActive();
        $currency = Currency::getDefaultCurrency();
        $saleElementId = (int) $product->getDefaultSaleElements()->getId();

        $statements = $this->recordSqlQueries(function () use ($product, $currency, $saleElementId): void {
            self::assertSame(PriceDisplayMode::Visible, $this->resolver->modeFor($product, null));
            self::assertSame(PriceDisplayMode::Visible, $this->resolver->modeOfProductId((int) $product->getId()));
            self::assertSame([], $this->resolver->productsWithHiddenPrice([(int) $product->getId()], null));
            self::assertNull($this->resolver->shownPriceClause(ProductTableMap::COL_ID, null));
            self::assertSame([], $this->resolver->unpurchasableSaleElements([$saleElementId], null, $currency));
            self::assertFalse($this->resolver->mayRefuseAPurchase());
        });

        self::assertSame([], $statements);
    }

    public function testTheClauseKeepsTheProductsWhoseVisitorIsShownThePrice(): void
    {
        $shop = $this->pricedProduct();
        $visible = $this->pricedProduct();
        $loggedIn = $this->pricedProduct();
        $onRequest = $this->pricedProduct();
        $customer = $this->memberOf($this->factory->customerGroup());
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $this->productShows($visible, PriceDisplayMode::Visible);
        $this->productShows($loggedIn, PriceDisplayMode::LoggedIn);
        $this->productShows($onRequest, PriceDisplayMode::OnRequest);
        $mine = [$shop, $visible, $loggedIn, $onRequest];
        $idsOf = fn (?\Thelia\Model\Customer $who): array => array_map(
            static fn ($product): int => (int) $product->getId(),
            array_values(array_filter($mine, fn ($product): bool => ProductQuery::create()
                ->filterById($product->getId())
                ->where((string) $this->resolver->shownPriceClause(ProductTableMap::COL_ID, $who))
                ->exists())),
        );

        self::assertSame([(int) $visible->getId()], $idsOf(null));
        self::assertSame([(int) $shop->getId(), (int) $visible->getId(), (int) $loggedIn->getId()], $idsOf($customer));
    }

    public function testASaleElementOfAProductNotShownThePriceIsNotPurchasable(): void
    {
        $open = $this->pricedProduct();
        $closed = $this->pricedProduct();
        $customer = $this->memberOf($this->factory->customerGroup());
        $this->productShows($closed, PriceDisplayMode::LoggedIn);
        $currency = Currency::getDefaultCurrency();
        $ids = [(int) $open->getDefaultSaleElements()->getId(), (int) $closed->getDefaultSaleElements()->getId()];

        self::assertSame([$ids[1]], $this->resolver->unpurchasableSaleElements($ids, null, $currency));
        self::assertSame([], $this->resolver->unpurchasableSaleElements($ids, $customer, $currency));

        $this->productShows($closed, PriceDisplayMode::OnRequest);

        self::assertSame([$ids[1]], $this->resolver->unpurchasableSaleElements($ids, $customer, $currency));
    }

    /**
     * Acceptance 4: the exclusive list prices A and B, the catalog holds A, B and C.
     */
    public function testASaleElementAnExclusiveListDoesNotPriceIsNotPurchasable(): void
    {
        $product = $this->pricedProduct();
        $pseA = $product->getDefaultSaleElements();
        $pseB = $this->factory->productSaleElement($product);
        $pseC = $this->factory->productSaleElement($product);
        $this->factory->productPrice($pseB, Currency::getDefaultCurrency());
        $this->factory->productPrice($pseC, Currency::getDefaultCurrency());
        $group = $this->factory->customerGroup();
        $member = $this->memberOf($group);
        $list = $this->priceList('EXCL', exclusive: true);
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pseA, '50');
        $this->priceListItem($list, $pseB, '60');
        $this->forgetPriceDisplayMemos();
        $ids = [(int) $pseA->getId(), (int) $pseB->getId(), (int) $pseC->getId()];

        self::assertSame([$ids[2]], $this->resolver->unpurchasableSaleElements($ids, $member, Currency::getDefaultCurrency()));
        self::assertSame([], $this->resolver->unpurchasableSaleElements($ids, null, Currency::getDefaultCurrency()), 'the list is not assigned to a visitor');
    }

    /**
     * An exclusive list that holds the sale element from ten units up holds it: the buyer may add it at any
     * quantity (below the tier at the price the chain gives, from the tier at the price of the list), and
     * it stays closed to the buyer whose list does not hold it at all.
     */
    public function testAnExclusiveListThatHoldsASaleElementFromATierUpLeavesItForSaleAtEveryQuantity(): void
    {
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        $other = $this->pricedProduct()->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $member = $this->memberOf($group);
        $list = $this->priceList('EXCL', exclusive: true);
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, '50', 10);
        $this->forgetPriceDisplayMemos();
        $id = (int) $pse->getId();
        $otherId = (int) $other->getId();

        self::assertSame([], $this->resolver->unpurchasableSaleElements([$id], $member, Currency::getDefaultCurrency(), [$id => 9]));
        self::assertSame([], $this->resolver->unpurchasableSaleElements([$id], $member, Currency::getDefaultCurrency(), [$id => 10]));
        self::assertSame([$otherId], $this->resolver->unpurchasableSaleElements([$id, $otherId], $member, Currency::getDefaultCurrency(), [$id => 10, $otherId => 10]));
    }

    /**
     * @param list<string> $statements
     *
     * @return list<string>
     */
    private static function selectingFromProduct(array $statements): array
    {
        return array_values(array_filter($statements, static fn (string $statement): bool => 1 === preg_match('/FROM\s+`?product`?(\s|$)/i', $statement) && !str_contains($statement, 'AS non_visible_price_display')));
    }
}
