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

use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\CustomerPrice\CustomerPriceContextInterface;
use Thelia\Domain\Pricing\PriceList\PriceListResolverInterface;
use Thelia\Model\Currency;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Integration\Domain\Pricing\PriceList\PriceListFixtures;

/**
 * The tiers a product page shows: every line of the first list that prices the sale element for
 * the customer, whatever the quantity.
 */
final class PriceListTiersTest extends ActionIntegrationTestCase
{
    use PriceListFixtures;
    use RecordsSqlQueries;

    private PriceListResolverInterface $resolver;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currency = Currency::getDefaultCurrency();
        $this->forgetRequestMemos();
        $this->resolver = $this->getService(PriceListResolverInterface::class);
    }

    protected function tearDown(): void
    {
        $this->forgetRequestMemos();

        parent::tearDown();
    }

    /**
     * The example of the brief: lines 1 -> 10 and 10 -> 9.
     */
    public function testEveryLineOfTheListIsATierTheSmallestMinimumQuantityFirst(): void
    {
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $list = $this->priceList('PRO');
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, '9', 10);
        $this->priceListItem($list, $pse, '10', 1);

        $tiers = $this->resolver->tiersFor([$pse->getId()], $this->currency, $this->memberOf($group));

        self::assertSame([(int) $pse->getId() => [['minQuantity' => 1, 'untaxedPrice' => '10.000000'], ['minQuantity' => 10, 'untaxedPrice' => '9.000000']]], $tiers);
    }

    /**
     * A list whose only line starts at 10: the tier is told, the price of the first tier is the
     * price outside the list (the resolver answers no list at quantity 1).
     */
    public function testAListWhoseLowestMinimumQuantityIsAboveOneIsListedAllTheSame(): void
    {
        $pse = $this->pricedProduct(100.0)->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $member = $this->memberOf($group);
        $list = $this->priceList();
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, '9', 10);

        self::assertSame([(int) $pse->getId() => [['minQuantity' => 10, 'untaxedPrice' => '9.000000']]], $this->resolver->tiersFor([$pse->getId()], $this->currency, $member));
        self::assertEqualsWithDelta(100.0, $this->getService(CustomerPriceContextInterface::class)->resolve([$pse->getId()], $this->currency, $member)[$pse->getId()]->untaxedPrice, 0.000001);
    }

    public function testALineInPercentIsPricedOnTheCatalogPriceAsTheResolverDoes(): void
    {
        $pse = $this->pricedProduct(100.0)->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $list = $this->priceList();
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, null, 1, '15');
        $this->priceListItem($list, $pse, '70', 10);

        $tiers = $this->resolver->tiersFor([$pse->getId()], $this->currency, $this->memberOf($group));

        self::assertSame([['minQuantity' => 1, 'untaxedPrice' => '85.000000'], ['minQuantity' => 10, 'untaxedPrice' => '70.000000']], $tiers[(int) $pse->getId()]);
    }

    public function testTheTiersAreThoseOfTheFirstListThenThoseOfTheListsBelowItsFirstTier(): void
    {
        $product = $this->pricedProduct();
        $pseA = $product->getDefaultSaleElements();
        $pseB = $this->factory->productSaleElement($product);
        $group = $this->factory->customerGroup();
        $customer = $this->memberOf($group);
        $groupList = $this->priceList('GROUP', priority: 30);
        $customerList = $this->priceList('CUSTOMER', priority: 10);
        $this->assignToGroup($groupList, $group);
        $this->assignToCustomer($customerList, $customer);
        $this->priceListItem($groupList, $pseA, '50');
        $this->priceListItem($groupList, $pseA, '48', 5);
        $this->priceListItem($groupList, $pseB, '60');
        $this->priceListItem($customerList, $pseA, '45', 2);

        $tiers = $this->resolver->tiersFor([$pseA->getId(), $pseB->getId()], $this->currency, $customer);

        self::assertSame([['minQuantity' => 1, 'untaxedPrice' => '50.000000'], ['minQuantity' => 2, 'untaxedPrice' => '45.000000']], $tiers[(int) $pseA->getId()], 'A: the list of the customer (rank 10) from 2 units, the group list below it: its line for 1 unit applies, its line for 5 never does');
        self::assertSame([['minQuantity' => 1, 'untaxedPrice' => '60.000000']], $tiers[(int) $pseB->getId()], 'B: only the group list holds it');
    }

    /**
     * Exclusive E holds A from 10 units (10 -> 9), the list L of lower rank prices it 1 -> 7 and 5 -> 6:
     * the cart charges 7 at 1 unit, 6 at 5 and 9 at 10, and the tiers say exactly that.
     */
    public function testTheTiersBelowTheFirstTierOfAnExclusiveListAreThoseOfTheChainBelow(): void
    {
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $member = $this->memberOf($group);
        $exclusive = $this->priceList('E', priority: 10, exclusive: true);
        $lower = $this->priceList('L', priority: 20);
        $this->assignToGroup($exclusive, $group);
        $this->assignToGroup($lower, $group);
        $this->priceListItem($exclusive, $pse, '9', 10);
        $this->priceListItem($lower, $pse, '7', 1);
        $this->priceListItem($lower, $pse, '6', 5);

        $tiers = $this->resolver->tiersFor([$pse->getId()], $this->currency, $member)[(int) $pse->getId()];
        $context = $this->getService(CustomerPriceContextInterface::class);
        $priceAt = fn (int $quantity): float => $context->resolve([$pse->getId()], $this->currency, $member, [$pse->getId() => $quantity])[$pse->getId()]->untaxedPrice;

        self::assertSame([['minQuantity' => 1, 'untaxedPrice' => '7.000000'], ['minQuantity' => 5, 'untaxedPrice' => '6.000000'], ['minQuantity' => 10, 'untaxedPrice' => '9.000000']], $tiers);
        self::assertEqualsWithDelta(7.0, $priceAt(1), 0.000001);
        self::assertEqualsWithDelta(7.0, $priceAt(4), 0.000001);
        self::assertEqualsWithDelta(6.0, $priceAt(5), 0.000001);
        self::assertEqualsWithDelta(6.0, $priceAt(9), 0.000001);
        self::assertEqualsWithDelta(9.0, $priceAt(10), 0.000001);
    }

    /**
     * A lower line from a quantity the first list already prices is never applied: it is not told.
     */
    public function testALineOfTheListBelowAtOrAboveTheFirstTierOfTheListAboveIsNeverTold(): void
    {
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $member = $this->memberOf($group);
        $first = $this->priceList('FIRST', priority: 10);
        $lower = $this->priceList('LOWER', priority: 20);
        $this->assignToGroup($first, $group);
        $this->assignToGroup($lower, $group);
        $this->priceListItem($first, $pse, '9', 5);
        $this->priceListItem($lower, $pse, '7', 2);
        $this->priceListItem($lower, $pse, '6', 5);
        $this->priceListItem($lower, $pse, '5', 8);

        self::assertSame(
            [['minQuantity' => 2, 'untaxedPrice' => '7.000000'], ['minQuantity' => 5, 'untaxedPrice' => '9.000000']],
            $this->resolver->tiersFor([$pse->getId()], $this->currency, $member)[(int) $pse->getId()],
        );
    }

    public function testAnExclusiveListThatDoesNotHoldTheSaleElementLeavesItWithoutTierAndALowerListDoesNotOpenIt(): void
    {
        $product = $this->pricedProduct();
        $pseA = $product->getDefaultSaleElements();
        $pseB = $this->factory->productSaleElement($product);
        $group = $this->factory->customerGroup();
        $exclusive = $this->priceList('EXCL', priority: 10, exclusive: true);
        $lower = $this->priceList('LOWER', priority: 20);
        $this->assignToGroup($exclusive, $group);
        $this->assignToGroup($lower, $group);
        $this->priceListItem($exclusive, $pseA, '50');
        $this->priceListItem($lower, $pseB, '30');

        $tiers = $this->resolver->tiersFor([$pseA->getId(), $pseB->getId()], $this->currency, $this->memberOf($group));

        self::assertArrayHasKey((int) $pseA->getId(), $tiers);
        self::assertArrayNotHasKey((int) $pseB->getId(), $tiers);
    }

    public function testAVisitorAndACustomerOutsideEveryListHaveNoTier(): void
    {
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $list = $this->priceList();
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, '50');

        self::assertSame([], $this->resolver->tiersFor([$pse->getId()], $this->currency, null));
        self::assertSame([], $this->resolver->tiersFor([$pse->getId()], $this->currency, $this->memberOf($this->factory->customerGroup())));
        self::assertSame([], $this->resolver->tiersFor([], $this->currency, null));
    }

    public function testAListInAnotherCurrencyIsNotRead(): void
    {
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $list = $this->priceList(currency: $this->factory->currency(['code' => 'USD', 'symbol' => '$', 'rate' => 1.1]));
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, '50');

        self::assertSame([], $this->resolver->tiersFor([$pse->getId()], $this->currency, $this->memberOf($group)));
    }

    public function testAShopWithoutPriceListCostsNoStatement(): void
    {
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        $member = $this->memberOf($this->factory->customerGroup());
        $this->forgetRequestMemos();
        $this->getService(CatalogActivityProbe::class)->read();

        $statements = $this->recordSqlQueries(function () use ($pse, $member): void {
            self::assertSame([], $this->resolver->tiersFor([$pse->getId()], $this->currency, $member));
        });

        self::assertSame([], $statements);
    }

    /**
     * A listing asks once for its page, then one sale element at a time: the lines are read once.
     */
    public function testThePageIsReadOnceAndEachSaleElementIsThenAnsweredFromMemory(): void
    {
        $product = $this->pricedProduct();
        $saleElements = [$product->getDefaultSaleElements(), $this->factory->productSaleElement($product), $this->factory->productSaleElement($product)];
        $group = $this->factory->customerGroup();
        $member = $this->memberOf($group);
        $list = $this->priceList();
        $this->assignToGroup($list, $group);

        foreach ($saleElements as $saleElement) {
            $this->priceListItem($list, $saleElement, '50');
        }

        $this->forgetRequestMemos();
        $ids = array_map(static fn ($saleElement): int => (int) $saleElement->getId(), $saleElements);

        $statements = $this->recordSqlQueries(function () use ($ids, $member): void {
            $this->resolver->tiersFor($ids, $this->currency, $member);

            foreach ($ids as $id) {
                self::assertCount(1, $this->resolver->tiersFor([$id], $this->currency, $member)[$id]);
            }
        });

        self::assertSame(1, self::countSqlQueriesSelectingFrom($statements, 'price_list_item'));
    }
}
