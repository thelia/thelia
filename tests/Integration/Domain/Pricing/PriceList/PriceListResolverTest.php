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

namespace Thelia\Tests\Integration\Domain\Pricing\PriceList;

use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\PriceList\PriceListMatch;
use Thelia\Domain\Pricing\PriceList\PriceListResolverInterface;
use Thelia\Model\Currency;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * Which line of which price list prices a sale element for a customer, at a quantity.
 */
final class PriceListResolverTest extends ActionIntegrationTestCase
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

    public function testTheLineWithTheGreatestMinimumQuantityNotAboveTheQuantityPrices(): void
    {
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $member = $this->memberOf($group);
        $list = $this->priceList('PRO');
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, '10', 1);
        $this->priceListItem($list, $pse, '9', 10);
        $this->priceListItem($list, $pse, '8.5', 50);

        $priceAt = fn (int $quantity): ?string => $this->resolver->resolve([$pse->getId()], $this->currency, $member, [$pse->getId() => $quantity])[$pse->getId()]->untaxedPrice;

        self::assertSame('10.000000', $priceAt(1));
        self::assertSame('10.000000', $priceAt(9));
        self::assertSame('9.000000', $priceAt(10));
        self::assertSame('9.000000', $priceAt(49));
        self::assertSame('8.500000', $priceAt(50));
        self::assertSame('10.000000', $this->resolver->resolve([$pse->getId()], $this->currency, $member)[$pse->getId()]->untaxedPrice, 'a missing quantity is 1');
    }

    /**
     * The example of the contract: lines 1 -> 10 and 10 -> 9, asked 10 units.
     */
    public function testTheMatchCarriesTheListTheLineAndEveryTierOfTheList(): void
    {
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $list = $this->priceList('PRO');
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, '9', 10);
        $this->priceListItem($list, $pse, '10', 1);

        $match = $this->resolver->resolve([$pse->getId()], $this->currency, $this->memberOf($group), [$pse->getId() => 10])[$pse->getId()];

        self::assertEquals(
            new PriceListMatch((int) $list->getId(), 'PRO', '9.000000', true, 10, [['minQuantity' => 1, 'untaxedPrice' => '10.000000'], ['minQuantity' => 10, 'untaxedPrice' => '9.000000']]),
            $match,
        );
    }

    public function testAQuantityBelowEveryMinimumQuantityIsNotPricedByTheList(): void
    {
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $list = $this->priceList();
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, '7', 5);

        self::assertSame([], $this->resolver->resolve([$pse->getId()], $this->currency, $this->memberOf($group), [$pse->getId() => 4]));
        self::assertSame('7.000000', $this->resolver->resolve([$pse->getId()], $this->currency, $this->memberOf($group), [$pse->getId() => 5])[$pse->getId()]->untaxedPrice);
    }

    /**
     * Acceptance 3: the list of the group G (rank 30) prices A 50 and B 60, the list of the
     * customer C (rank 10) prices A 45.
     */
    public function testTheListOfTheBestRankPricesWhatItHasAndTheOtherPricesTheRest(): void
    {
        $product = $this->pricedProduct();
        $pseA = $product->getDefaultSaleElements();
        $pseB = $this->factory->productSaleElement($product);
        $pseC = $this->factory->productSaleElement($product);
        $group = $this->factory->customerGroup();
        $customer = $this->memberOf($group);
        $groupList = $this->priceList('GROUP', priority: 30);
        $customerList = $this->priceList('CUSTOMER', priority: 10);
        $this->assignToGroup($groupList, $group);
        $this->assignToCustomer($customerList, $customer);
        $this->priceListItem($groupList, $pseA, '50');
        $this->priceListItem($groupList, $pseB, '60');
        $this->priceListItem($customerList, $pseA, '45');

        $matches = $this->resolver->resolve([$pseA->getId(), $pseB->getId(), $pseC->getId()], $this->currency, $customer);

        self::assertSame('45.000000', $matches[$pseA->getId()]->untaxedPrice);
        self::assertSame('CUSTOMER', $matches[$pseA->getId()]->priceListCode);
        self::assertSame('60.000000', $matches[$pseB->getId()]->untaxedPrice);
        self::assertSame('GROUP', $matches[$pseB->getId()]->priceListCode);
        self::assertArrayNotHasKey($pseC->getId(), $matches, 'C keeps the catalog price');
    }

    public function testATieOnThePriorityGoesToTheListAssignedToTheCustomerThenToTheOldestList(): void
    {
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $customer = $this->memberOf($group);
        $oldGroupList = $this->priceList('OLD-GROUP', priority: 10);
        $newGroupList = $this->priceList('NEW-GROUP', priority: 10);
        $customerList = $this->priceList('CUSTOMER', priority: 10);

        foreach ([$oldGroupList, $newGroupList] as $list) {
            $this->assignToGroup($list, $group);
            $this->priceListItem($list, $pse, '50');
        }

        $this->assignToCustomer($customerList, $customer);
        $this->priceListItem($customerList, $pse, '48');

        self::assertSame('CUSTOMER', $this->resolver->resolve([$pse->getId()], $this->currency, $customer)[$pse->getId()]->priceListCode);

        $other = $this->memberOf($group);
        $this->forgetRequestMemos();

        self::assertSame('OLD-GROUP', $this->resolver->resolve([$pse->getId()], $this->currency, $other)[$pse->getId()]->priceListCode);
    }

    /**
     * Acceptance 4: the exclusive list prices A and B, the catalog holds A, B and C.
     */
    public function testAnExclusiveListClosesWhatItDoesNotPrice(): void
    {
        $product = $this->pricedProduct();
        $pseA = $product->getDefaultSaleElements();
        $pseB = $this->factory->productSaleElement($product);
        $pseC = $this->factory->productSaleElement($product);
        $group = $this->factory->customerGroup();
        $list = $this->priceList('EXCL', exclusive: true);
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pseA, '50');
        $this->priceListItem($list, $pseB, '60');

        $matches = $this->resolver->resolve([$pseA->getId(), $pseB->getId(), $pseC->getId()], $this->currency, $this->memberOf($group));

        self::assertTrue($matches[$pseA->getId()]->purchasable);
        self::assertTrue($matches[$pseB->getId()]->purchasable);
        self::assertEquals(new PriceListMatch((int) $list->getId(), 'EXCL', null, false, 0, []), $matches[$pseC->getId()]);
    }

    public function testAListOfHigherRankOpensWhatTheExclusiveListClosesAndALowerOneDoesNot(): void
    {
        $product = $this->pricedProduct();
        $pseA = $product->getDefaultSaleElements();
        $pseB = $this->factory->productSaleElement($product);
        $group = $this->factory->customerGroup();
        $member = $this->memberOf($group);
        $higher = $this->priceList('HIGHER', priority: 5);
        $exclusive = $this->priceList('EXCL', priority: 10, exclusive: true);
        $lower = $this->priceList('LOWER', priority: 20);

        foreach ([$higher, $exclusive, $lower] as $list) {
            $this->assignToGroup($list, $group);
        }

        $this->priceListItem($higher, $pseA, '40');
        $this->priceListItem($lower, $pseB, '30');

        $matches = $this->resolver->resolve([$pseA->getId(), $pseB->getId()], $this->currency, $member);

        self::assertSame('HIGHER', $matches[$pseA->getId()]->priceListCode);
        self::assertTrue($matches[$pseA->getId()]->purchasable);
        self::assertSame('EXCL', $matches[$pseB->getId()]->priceListCode);
        self::assertFalse($matches[$pseB->getId()]->purchasable);
    }

    /**
     * the sale elements an exclusive list holds are the ones its customer may buy, in whatever
     * currency they browse: the list prices them in its own, and in another one it only closes the rest.
     */
    public function testAnExclusiveListClosesWhatItDoesNotHoldInEveryCurrencyAndPricesInItsOwnOnly(): void
    {
        $product = $this->pricedProduct();
        $pseA = $product->getDefaultSaleElements();
        $pseB = $this->factory->productSaleElement($product);
        $pseC = $this->factory->productSaleElement($product);
        $group = $this->factory->customerGroup();
        $member = $this->memberOf($group);
        $dollar = $this->factory->currency(['code' => 'USD', 'symbol' => '$', 'rate' => 1.1]);
        $list = $this->priceList('EXCL', $this->currency, exclusive: true);
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pseA, '50');
        $this->priceListItem($list, $pseB, null, 1, '10');
        $ids = [$pseA->getId(), $pseB->getId(), $pseC->getId()];

        $inDollars = [];
        $statements = $this->recordSqlQueries(function () use (&$inDollars, $ids, $dollar, $member): void {
            $inDollars = $this->resolver->resolve($ids, $dollar, $member);
        });
        self::assertSame(0, self::countSqlQueriesSelectingFrom($statements, 'product_sale_elements'), 'a line in % of a list in euros asks no catalog price while the buyer is in dollars');
        self::assertArrayNotHasKey($pseA->getId(), $inDollars, 'held: bought at the price the chain below gives, in dollars');
        self::assertArrayNotHasKey($pseB->getId(), $inDollars);
        self::assertEquals(new PriceListMatch((int) $list->getId(), 'EXCL', null, false, 0, []), $inDollars[$pseC->getId()], 'not held: closed in dollars as in euros');
        self::assertSame([], $this->resolver->tiersFor($ids, $dollar, $member), 'the list has no price in dollars');

        $inEuros = $this->resolver->resolve($ids, $this->currency, $member);
        self::assertSame('50.000000', $inEuros[$pseA->getId()]->untaxedPrice);
        self::assertFalse($inEuros[$pseC->getId()]->purchasable);
    }

    public function testAnExclusiveListInAnotherCurrencyDoesNotStopAListOfRankBelowFromPricingWhatItHolds(): void
    {
        $product = $this->pricedProduct();
        $pseA = $product->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $member = $this->memberOf($group);
        $dollar = $this->factory->currency(['code' => 'USD', 'symbol' => '$', 'rate' => 1.1]);
        $exclusive = $this->priceList('EXCL-EUR', $this->currency, priority: 10, exclusive: true);
        $below = $this->priceList('BELOW-USD', $dollar, priority: 20);

        foreach ([$exclusive, $below] as $list) {
            $this->assignToGroup($list, $group);
        }

        $this->priceListItem($exclusive, $pseA, '50');
        $this->priceListItem($below, $pseA, '44');

        $match = $this->resolver->resolve([$pseA->getId()], $dollar, $member)[$pseA->getId()];

        self::assertSame('BELOW-USD', $match->priceListCode);
        self::assertSame('44.000000', $match->untaxedPrice);
        self::assertTrue($match->purchasable);
        self::assertSame([['minQuantity' => 1, 'untaxedPrice' => '44.000000']], $this->resolver->tiersFor([$pseA->getId()], $dollar, $member)[$pseA->getId()], 'the exclusive list holds the sale element, so it does not stop the tiers of the list below');
    }

    /**
     * An exclusive list that holds the sale element only from a quantity up does not close it below that quantity:
     * it is bought at the price the chain below gives, and the list prices it from the tier up.
     */
    public function testAnExclusiveListThatHoldsASaleElementFromATierUpDoesNotCloseItBelowTheTier(): void
    {
        $product = $this->pricedProduct();
        $pseX = $product->getDefaultSaleElements();
        $pseC = $this->factory->productSaleElement($product);
        $group = $this->factory->customerGroup();
        $member = $this->memberOf($group);
        $list = $this->priceList('EXCL', exclusive: true);
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pseX, '9', 10);
        $ids = [$pseX->getId(), $pseC->getId()];

        $below = $this->resolver->resolve($ids, $this->currency, $member, [$pseX->getId() => 1]);
        self::assertArrayNotHasKey($pseX->getId(), $below, 'held, not priced by the list at this quantity: bought at the chain price');
        self::assertFalse($below[$pseC->getId()]->purchasable);

        $atTheTier = $this->resolver->resolve($ids, $this->currency, $member, [$pseX->getId() => 10]);
        self::assertSame('9.000000', $atTheTier[$pseX->getId()]->untaxedPrice);
        self::assertSame([['minQuantity' => 10, 'untaxedPrice' => '9.000000']], $this->resolver->tiersFor($ids, $this->currency, $member)[$pseX->getId()], 'the tiers of the list stay published');
    }

    public function testAListThatIsOffTooEarlyOverOrInAnotherCurrencyAppliesToNobody(): void
    {
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $member = $this->memberOf($group);
        $dollar = $this->factory->currency(['code' => 'USD', 'symbol' => '$', 'rate' => 1.1]);

        $lists = [
            $this->priceList(active: false),
            $this->priceList(startsAt: new \DateTimeImmutable('+1 hour')),
            $this->priceList(endsAt: new \DateTimeImmutable('-1 hour')),
            $this->priceList(currency: $dollar),
        ];

        foreach ($lists as $list) {
            $this->assignToGroup($list, $group);
            $this->priceListItem($list, $pse, '1');
        }

        self::assertSame([], $this->resolver->resolve([$pse->getId()], $this->currency, $member));

        $open = $this->priceList(startsAt: new \DateTimeImmutable('-1 hour'), endsAt: new \DateTimeImmutable('+1 hour'));
        $this->assignToGroup($open, $group);
        $this->priceListItem($open, $pse, '33');
        $this->forgetRequestMemos();

        self::assertSame('33.000000', $this->resolver->resolve([$pse->getId()], $this->currency, $member)[$pse->getId()]->untaxedPrice);
    }

    public function testAListIsOnlyForTheGroupsAndTheCustomersItIsAssignedTo(): void
    {
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $list = $this->priceList();
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, '70');

        self::assertCount(1, $this->resolver->resolve([$pse->getId()], $this->currency, $this->memberOf($group)));
        self::assertSame([], $this->resolver->resolve([$pse->getId()], $this->currency, $this->memberOf($this->factory->customerGroup())));
        self::assertSame([], $this->resolver->resolve([$pse->getId()], $this->currency, null));
    }

    public function testAVisitorAndAGuestAreInTheDefaultGroupAndAGuestHasNoListOfTheirOwn(): void
    {
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        $guest = $this->factory->guestCustomer($this->factory->customerTitle());
        $list = $this->priceList('DEFAULT');
        $this->assignToGroup($list, CustomerGroupQuery::findDefault());
        $this->priceListItem($list, $pse, '90');
        $ownList = $this->priceList('OWN', priority: 1);
        $this->assignToCustomer($ownList, $guest);
        $this->priceListItem($ownList, $pse, '10');

        self::assertSame('DEFAULT', $this->resolver->resolve([$pse->getId()], $this->currency, null)[$pse->getId()]->priceListCode);
        self::assertSame('DEFAULT', $this->resolver->resolve([$pse->getId()], $this->currency, $guest)[$pse->getId()]->priceListCode);
    }

    /**
     * A line in % applies to the catalog price, never to the promo price: catalog 100, promo 70, line -15 % -> 85.
     */
    public function testALineInPercentAppliesToTheCatalogPriceNotToThePromotion(): void
    {
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        ProductPriceQuery::create()->filterByProductSaleElementsId($pse->getId())->update(['PromoPrice' => '70']);
        $group = $this->factory->customerGroup();
        $list = $this->priceList();
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, null, 1, '15');
        $this->priceListItem($list, $pse, null, 10, '30');

        $match = $this->resolver->resolve([$pse->getId()], $this->currency, $this->memberOf($group))[$pse->getId()];

        self::assertSame('85.000000', $match->untaxedPrice);
        self::assertSame([['minQuantity' => 1, 'untaxedPrice' => '85.000000'], ['minQuantity' => 10, 'untaxedPrice' => '70.000000']], $match->tiers);
    }

    public function testALineInPercentOfASaleElementTheCatalogDoesNotPriceIsNoLine(): void
    {
        $product = $this->pricedProduct();
        $unpriced = $this->factory->productSaleElement($product);
        $group = $this->factory->customerGroup();
        $list = $this->priceList();
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $unpriced, null, 1, '15');

        self::assertSame([], $this->resolver->resolve([$unpriced->getId()], $this->currency, $this->memberOf($group)));
    }

    public function testAPriceRoundsToSixDecimalsAsAString(): void
    {
        $pse = $this->pricedProduct(33.333333)->getDefaultSaleElements();
        $group = $this->factory->customerGroup();
        $list = $this->priceList();
        $this->assignToGroup($list, $group);
        $this->priceListItem($list, $pse, null, 1, '33.333333');

        self::assertSame('22.222222', $this->resolver->resolve([$pse->getId()], $this->currency, $this->memberOf($group))[$pse->getId()]->untaxedPrice);
    }

    public function testTwentySaleElementsCostTwoStatementsAndAskingAgainCostsNone(): void
    {
        $group = $this->factory->customerGroup();
        $member = $this->memberOf($group);
        $list = $this->priceList();
        $this->assignToGroup($list, $group);
        $ids = [];

        for ($i = 0; $i < 20; ++$i) {
            $pse = $this->pricedProduct()->getDefaultSaleElements();
            $this->priceListItem($list, $pse, (string) (50 + $i));
            $ids[] = (int) $pse->getId();
        }

        $this->forgetRequestMemos();
        // The activity of the shop and the group of the customer are read once per request, whatever the batch.
        $this->getService(CatalogActivityProbe::class)->read();
        $this->getService(CustomerGroupResolver::class)->groupIdOf($member);

        $statements = $this->recordSqlQueries(fn () => $this->resolver->resolve($ids, $this->currency, $member));
        $again = $this->recordSqlQueries(fn () => $this->resolver->resolve($ids, $this->currency, $member));

        self::assertCount(20, $this->resolver->resolve($ids, $this->currency, $member));
        self::assertCount(2, $statements, implode("\n", $statements));
        self::assertSame(1, self::countSqlQueriesSelectingFrom($statements, 'price_list_item'), 'the lines of all the sale elements are read at once');
        self::assertSame([], $again);
    }

    public function testAShopWithoutPriceListCostsNoStatementAndPricesNothing(): void
    {
        $member = $this->memberOf($this->factory->customerGroup());
        $pse = $this->pricedProduct()->getDefaultSaleElements();
        // A list that is turned off is no list.
        $this->priceList(active: false);
        $this->forgetRequestMemos();
        $this->getService(CatalogActivityProbe::class)->read();

        $statements = $this->recordSqlQueries(function () use ($pse, $member): void {
            self::assertSame([], $this->resolver->resolve([$pse->getId()], $this->currency, $member));
        });

        self::assertSame([], $statements);
    }

    public function testAnEmptyAskReadsNothing(): void
    {
        self::assertSame([], $this->recordSqlQueries(function (): void {
            self::assertSame([], $this->resolver->resolve([], $this->currency, null));
        }));
    }
}
