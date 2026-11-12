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

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Event\ResourcePersistedEvent;
use Thelia\Api\Service\API\ResourceCache;
use Thelia\Api\Service\API\ResourceCacheInvalidationListener;
use Thelia\Domain\Catalog\Category\CategoryVisibility;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Catalog\Product\ProductVisibilityRuleInterface;
use Thelia\Domain\Catalog\Restriction\CatalogRestrictionRule;
use Thelia\Domain\Catalog\Restriction\CatalogScopeProbe;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Domain\Sale\ReservedSaleProductRule;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Model\CatalogRestriction;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\Event\ProductEvent;
use Thelia\Model\PriceList;
use Thelia\Model\PriceListAssignment;
use Thelia\Model\PriceListItem;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;
use Thelia\Tests\Integration\Domain\Pricing\PriceVisibility\PriceDisplayFixtures;
use Thelia\Tests\Support\Trait\ResetsPriceMemos;

/**
 * The cross-request cache of the data access layer with catalog restrictions and price lists in
 * the shop: assigned to a customer group, they put the group in the key (of the
 * brands and the categories for the restrictions, of the price paths for both), so that no group
 * is served the entry of another; assigned to a customer alone, they keep that customer out of the
 * cache, as a discount of their own does; assigned to nobody, the cache is what it was, and a
 * restriction is not mistaken for a rule of a module, which leaves the products out of it; and
 * every write to those tables is heard by the invalidation listener.
 */
final class ResourceCacheCatalogScopeTest extends IntegrationTestCase
{
    use LogsInAsCustomer;
    use PriceDisplayFixtures;
    use ResetsPriceMemos;

    private const BRANDS = '/api/front/brands/1';

    private const PRODUCTS = '/api/front/products/1';

    private FixtureFactory $factory;

    private Product $product;

    protected function setUp(): void
    {
        // Read when the cache service is built, which is after the kernel booted.
        putenv('THELIA_DATA_ACCESS_CACHE=1');
        $_ENV['THELIA_DATA_ACCESS_CACHE'] = $_SERVER['THELIA_DATA_ACCESS_CACHE'] = '1';

        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), Currency::getDefaultCurrency(), ['basePrice' => 100.0, 'baseQuantity' => 10]);
        $this->getService(ResourceCache::class)->clear();
        $this->forgetTheRequest();
    }

    protected function tearDown(): void
    {
        $this->getService(ResourceCache::class)->clear();
        $this->forgetTheRequest();
        putenv('THELIA_DATA_ACCESS_CACHE');
        unset($_ENV['THELIA_DATA_ACCESS_CACHE'], $_SERVER['THELIA_DATA_ACCESS_CACHE']);

        parent::tearDown();

        // The settings are read once per process: what a test wrote is gone with its transaction.
        $this->forgetPriceDisplayMemos();
    }

    /**
     * A price list that closes at 10:00 changes the prices of its group at 10:00 without any write the
     * invalidation could hear: the price entry of the group written at 09:59 is not served at 10:01.
     */
    public function testAPriceEntryDoesNotOutliveTheMomentTheListOfItsGroupCloses(): void
    {
        $group = $this->factory->customerGroup();
        $list = $this->priceList();
        $list->setEndsAt(new \DateTimeImmutable('+30 seconds'))->save($this->getPropelConnection());
        $this->assignPriceList($list, $group);
        $clock = new MockClock();
        $cache = $this->cacheOver([], $clock);
        $member = $this->member($group);

        self::assertSame('first', $this->readThrough($cache, $member, self::PRODUCTS, 'first'));
        $clock->sleep(20);
        self::assertSame('first', $this->readThrough($cache, $member, self::PRODUCTS, 'second'), 'before the list closes the entry is served');
        $clock->sleep(25);
        self::assertSame('third', $this->readThrough($cache, $member, self::PRODUCTS, 'third'), 'the list closed 15 seconds ago: the entry is gone, the lifetime of the cache being 60');
    }

    public function testAPriceEntryDoesNotOutliveTheMomentAListOfItsGroupOpensEither(): void
    {
        $group = $this->factory->customerGroup();
        $list = $this->priceList();
        $list->setStartsAt(new \DateTimeImmutable('+30 seconds'))->save($this->getPropelConnection());
        $this->assignPriceList($list, $group);
        $clock = new MockClock();
        $cache = $this->cacheOver([], $clock);
        $member = $this->member($group);

        self::assertSame('first', $this->readThrough($cache, $member, self::PRODUCTS, 'first'));
        $clock->sleep(40);
        self::assertSame('second', $this->readThrough($cache, $member, self::PRODUCTS, 'second'));
    }

    public function testAnEntryKeepsItsWholeLifetimeWhenNoListOpensOrCloses(): void
    {
        $group = $this->factory->customerGroup();
        $this->assignPriceList($this->priceList(), $group);
        $clock = new MockClock();
        $cache = $this->cacheOver([], $clock);
        $member = $this->member($group);

        self::assertSame('first', $this->readThrough($cache, $member, self::PRODUCTS, 'first'));
        $clock->sleep(50);
        self::assertSame('first', $this->readThrough($cache, $member, self::PRODUCTS, 'second'));
    }

    public function testThePriceListBoundaryIsTheNearestOfTheListsThatAreOnAndAhead(): void
    {
        $probe = $this->getService(CatalogScopeProbe::class);
        $this->forgetTheRequest();
        self::assertNull($probe->secondsBeforeAPriceListOpensOrCloses(), 'no price list');

        $this->priceList();
        $this->forgetTheRequest();
        self::assertNull($probe->secondsBeforeAPriceListOpensOrCloses(), 'a list with no date has no boundary');

        $far = $this->priceList();
        $far->setEndsAt(new \DateTimeImmutable('+2 hours'))->save($this->getPropelConnection());
        $near = $this->priceList();
        $near->setStartsAt(new \DateTimeImmutable('+10 minutes'))->save($this->getPropelConnection());
        $past = $this->priceList();
        $past->setEndsAt(new \DateTimeImmutable('-1 minute'))->save($this->getPropelConnection());
        $off = $this->priceList();
        $off->setActive(0)->setEndsAt(new \DateTimeImmutable('+1 minute'))->save($this->getPropelConnection());
        $this->forgetTheRequest();

        $seconds = $probe->secondsBeforeAPriceListOpensOrCloses();
        self::assertNotNull($seconds);
        self::assertEqualsWithDelta(600, $seconds, 3, 'the list that opens in ten minutes, not the one that closes in two hours, the one that closed, or the one turned off');
    }

    public function testNothingAssignedLeavesTheCacheAsItWasWhateverTheGroupAndEvenWithTheRestrictionRuleInTheVisibility(): void
    {
        $first = $this->member($this->factory->customerGroup());
        $second = $this->member($this->factory->customerGroup());

        foreach ([self::BRANDS, self::PRODUCTS] as $path) {
            self::assertSame('first', $this->read($first, $path, 'first'));
            self::assertSame('first', $this->read($second, $path, 'second'), 'served from the entry the shop shares: '.$path);
            self::assertSame('first', $this->read(null, $path, 'visitor'), 'the visitor too: '.$path);
        }
    }

    public function testARestrictionOfAGroupPutsTheGroupInTheKeyOfTheBrandsTheCategoriesAndTheProducts(): void
    {
        $restricted = $this->factory->customerGroup();
        $other = $this->factory->customerGroup();
        $this->restrictGroup($restricted);

        foreach ([self::BRANDS, '/api/front/categories', self::PRODUCTS] as $path) {
            self::assertSame('restricted', $this->read($this->member($restricted), $path, 'restricted'), $path);
            self::assertSame('other', $this->read($this->member($other), $path, 'other'), 'not served the entry of the restricted group: '.$path);
            self::assertSame('visitor', $this->read(null, $path, 'visitor'), 'the visitor is the default group, which has its own: '.$path);
            self::assertSame('restricted', $this->read($this->member($restricted), $path, 'again'), 'the group stays on the cache: '.$path);
            self::assertSame('other', $this->read($this->member($other), $path, 'again'), 'the group stays on the cache: '.$path);
        }
    }

    public function testARestrictionOfTheDefaultGroupKeepsTheVisitorsOnTheirOwnEntry(): void
    {
        $this->restrictGroup(CustomerGroupQuery::findDefault());
        $member = $this->member($this->factory->customerGroup());

        self::assertSame('member', $this->read($member, self::BRANDS, 'member'));
        self::assertSame('visitor', $this->read(null, self::BRANDS, 'visitor'));
        self::assertSame('member', $this->read($member, self::BRANDS, 'again'));
        self::assertSame('visitor', $this->read(null, self::BRANDS, 'again'));
    }

    public function testARestrictionOfACustomerAloneKeepsThatCustomerOutOfTheCache(): void
    {
        $group = $this->factory->customerGroup();
        $plain = $this->member($group);
        $alone = $this->member($group);
        $this->restrictCustomer($alone);

        foreach ([self::BRANDS, '/api/front/categories', self::PRODUCTS] as $path) {
            self::assertSame('plain', $this->read($plain, $path, 'plain'), $path);
            self::assertSame('alone', $this->read($alone, $path, 'alone'), 'not served the entry of the group: '.$path);
            self::assertSame('alone again', $this->read($alone, $path, 'alone again'), 'computed every time, written to no entry: '.$path);
            self::assertSame('plain', $this->read($plain, $path, 'again'), 'what the customer was shown is not in the entry of the group: '.$path);
        }
    }

    public function testAPriceListOfAGroupPutsTheGroupInTheKeyOfThePricePathsOnly(): void
    {
        $listed = $this->factory->customerGroup();
        $other = $this->factory->customerGroup();
        $this->assignPriceList($this->priceList(), group: $listed);

        self::assertSame('listed', $this->read($this->member($listed), self::PRODUCTS, 'listed'));
        self::assertSame('other', $this->read($this->member($other), self::PRODUCTS, 'other'));
        self::assertSame('visitor', $this->read(null, self::PRODUCTS, 'visitor'));
        self::assertSame('listed', $this->read($this->member($listed), self::PRODUCTS, 'again'));

        // A price list closes nothing: what is not a price stays on the entry everybody shares.
        self::assertSame('first', $this->read($this->member($listed), self::BRANDS, 'first'));
        self::assertSame('first', $this->read($this->member($other), self::BRANDS, 'second'));
    }

    public function testAPriceListOfACustomerAloneKeepsThatCustomerOutOfThePriceCache(): void
    {
        $group = $this->factory->customerGroup();
        $plain = $this->member($group);
        $alone = $this->member($group);
        $this->assignPriceList($this->priceList(), customer: $alone);

        self::assertSame('plain', $this->read($plain, self::PRODUCTS, 'plain'));
        self::assertSame('alone', $this->read($alone, self::PRODUCTS, 'alone'));
        self::assertSame('alone again', $this->read($alone, self::PRODUCTS, 'alone again'));
        self::assertSame('plain', $this->read($plain, self::PRODUCTS, 'again'));

        // The list closes nothing: the brands stay on the shared entry, for the customer too.
        self::assertSame('first', $this->read($plain, self::BRANDS, 'first'));
        self::assertSame('first', $this->read($alone, self::BRANDS, 'alone'));
    }

    public function testARuleDeclaredByAModuleStillLeavesTheProductsOutOfTheCacheWhileTheRestrictionRuleDoesNot(): void
    {
        $moduleRule = new class implements ProductVisibilityRuleInterface {
            public function visibleProductClause(string $productIdColumn, ?Customer $customer): ?string
            {
                return null;
            }
        };
        $group = $this->factory->customerGroup();
        $member = $this->member($group);

        $withTheRestrictionRuleOnly = $this->cacheOver([]);
        self::assertSame('first', $this->readThrough($withTheRestrictionRuleOnly, $member, self::PRODUCTS, 'first'));
        self::assertSame('first', $this->readThrough($withTheRestrictionRuleOnly, $member, self::PRODUCTS, 'second'), 'cached');

        $withAModuleRule = $this->cacheOver([$moduleRule]);
        self::assertSame('first', $this->readThrough($withAModuleRule, $member, self::PRODUCTS, 'first'));
        self::assertSame('second', $this->readThrough($withAModuleRule, $member, self::PRODUCTS, 'second'), 'a rule of a module may look at anything of the visitor: not cached');
    }

    public function testAWriteToAPriceListItsLinesItsAssignmentsOrARestrictionIsHeard(): void
    {
        $group = $this->factory->customerGroup();
        $list = $this->priceList();
        $this->warm();

        $line = (new PriceListItem())->setPriceListId($list->getId())->setProductSaleElementsId($this->product->getDefaultSaleElements()->getId())->setMinQuantity(1)->setPrice('80.000000');
        $line->save($this->getPropelConnection());
        $this->assertComputedAgain('a line written');

        $this->warm();
        $line->setPrice('70.000000')->save($this->getPropelConnection());
        $this->assertComputedAgain('a line changed');

        $this->warm();
        $line->delete($this->getPropelConnection());
        $this->assertComputedAgain('a line deleted');

        $this->warm();
        $assignment = $this->assignPriceList($list, group: $group);
        $this->assertComputedAgain('a list assigned');

        $this->warm();
        $assignment->delete($this->getPropelConnection());
        $this->assertComputedAgain('a list unassigned');

        $this->warm();
        $list->setPriority(5)->save($this->getPropelConnection());
        $this->assertComputedAgain('a list changed');

        $this->warm();
        $rule = $this->restrictGroup($group);
        $this->assertComputedAgain('a restriction written');

        $this->warm();
        $rule->delete($this->getPropelConnection());
        $this->assertComputedAgain('a restriction removed');

        $this->warm();
        $list->delete($this->getPropelConnection());
        $this->assertComputedAgain('a list deleted');
    }

    /**
     * The API writes the tables with no model event the listener hears before the commit: the event
     * the persist processor dispatches once the row is committed is what flushes the pool.
     */
    public function testAModelWrittenThroughTheApiFlushesThePoolOnceItsRequestEnds(): void
    {
        foreach ([new PriceList(), new PriceListItem(), new PriceListAssignment(), new CatalogRestriction()] as $model) {
            $this->warm();

            static::getContainer()->get(EventDispatcherInterface::class)->dispatch(
                new ResourcePersistedEvent($model::class, $model, ResourcePersistedEvent::OPERATION_WRITE),
            );

            $this->assertComputedAgain($model::class);
        }
    }

    public function testTheModelsWrittenThroughTheApiAreHeardToo(): void
    {
        $listener = $this->getService(ResourceCacheInvalidationListener::class);

        foreach ([new PriceList(), new PriceListItem(), new PriceListAssignment(), new CatalogRestriction()] as $model) {
            self::assertTrue($listener::isCatalogScopeModel($model), $model::class);
        }

        self::assertFalse($listener::isCatalogScopeModel(new Product()));
        self::assertSame(
            ['propel.post.save.price_list', 'propel.post.delete.price_list', 'propel.post.save.price_list_item', 'propel.post.delete.price_list_item', 'propel.post.save.price_list_assignment', 'propel.post.delete.price_list_assignment', 'propel.post.save.catalog_restriction', 'propel.post.delete.catalog_restriction'],
            $listener::CATALOG_SCOPE_WRITES,
        );
    }

    /**
     * A write that fails between the two events of a product leaves the listener holding the product
     * the whole life of a worker, unless the memory of the change is weak.
     */
    public function testAProductWhoseWriteFailedBetweenTheTwoEventsIsNotKeptByTheListener(): void
    {
        $listener = $this->getService(ResourceCacheInvalidationListener::class);
        $product = new Product();
        $product->setPriceDisplayMode(PriceDisplayMode::OnRequest->value);

        $listener->rememberTheModeChange(new ProductEvent($product));
        $changes = (new \ReflectionProperty($listener, 'modeChanges'))->getValue($listener);
        $remembered = (new \ReflectionProperty($changes, 'products'))->getValue($changes);
        self::assertCount(1, $remembered, 'the change is remembered while the write runs');

        unset($product);
        gc_collect_cycles();

        self::assertCount(0, $remembered, 'the write failed: nothing holds the product any more');
    }

    public function testAShopThatShowsItsPricesToIdentifiedCustomersOnlyNeverServesTheEntryOfACustomerToAVisitor(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $customer = $this->member(CustomerGroupQuery::findDefault());

        foreach ([self::PRODUCTS, '/api/front/product_sale_elements/1'] as $path) {
            self::assertSame('customer', $this->read($customer, $path, 'customer'), $path);
            self::assertSame('visitor', $this->read(null, $path, 'visitor'), 'the priced answer of the customer is not served to a visitor: '.$path);
            self::assertSame('customer', $this->read($customer, $path, 'again'), 'the customers stay on the cache: '.$path);
            self::assertSame('visitor', $this->read(null, $path, 'again'), 'the visitors stay on the cache: '.$path);
        }
    }

    public function testTheVisitorIsKeptApartFromTheCustomerWhoseGroupIsPricedToo(): void
    {
        $this->shopShows(PriceDisplayMode::OnRequest);
        $this->restrictGroup($this->factory->customerGroup());
        $customer = $this->member(CustomerGroupQuery::findDefault());

        self::assertSame('customer', $this->read($customer, self::PRODUCTS, 'customer'));
        self::assertSame('visitor', $this->read(null, self::PRODUCTS, 'visitor'));
    }

    public function testAProductThatStatesAModeOfItsOwnKeepsTheVisitorApartFromTheCustomerToo(): void
    {
        $this->productShows($this->product, PriceDisplayMode::LoggedIn);
        $customer = $this->member($this->factory->customerGroup());

        self::assertSame('customer', $this->read($customer, self::PRODUCTS, 'customer'));
        self::assertSame('visitor', $this->read(null, self::PRODUCTS, 'visitor'));
        self::assertSame('customer', $this->read($customer, self::PRODUCTS, 'again'));
    }

    public function testAShopThatShowsEveryPriceKeepsVisitorsAndCustomersOnOneEntry(): void
    {
        $customer = $this->member($this->factory->customerGroup());

        self::assertSame('first', $this->read($customer, self::PRODUCTS, 'first'));
        self::assertSame('first', $this->read(null, self::PRODUCTS, 'visitor'), 'nothing is hidden: one entry for everybody');
    }

    public function testWhatIsNotAPriceStaysOnTheEntryEverybodySharesWhateverTheMode(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $customer = $this->member($this->factory->customerGroup());

        self::assertSame('first', $this->read($customer, self::BRANDS, 'first'));
        self::assertSame('first', $this->read(null, self::BRANDS, 'visitor'));
    }

    public function testAWriteToThePriceDisplayModeOfAProductOrOfTheShopIsHeard(): void
    {
        $this->warm();
        $this->product->setPriceDisplayMode(PriceDisplayMode::OnRequest->value)->save($this->getPropelConnection());
        $this->assertComputedAgain('the mode of a product written');

        $this->warm();
        $this->product->setPriceDisplayMode(null)->save($this->getPropelConnection());
        $this->assertComputedAgain('the mode of a product removed');

        $this->warm();
        $this->shopShows(PriceDisplayMode::LoggedIn);
        $this->assertComputedAgain('the mode of the shop written');
    }

    public function testAnotherWriteToAProductOrAnotherSettingLeavesThePoolAlone(): void
    {
        $this->warm();
        $this->product->setPosition(((int) $this->product->getPosition()) + 5)->save($this->getPropelConnection());
        ConfigQuery::write('some_other_setting_'.uniqid(), 'x', false);
        static::getContainer()->get(EventDispatcherInterface::class)->dispatch(
            new TerminateEvent(static::getContainer()->get('kernel'), new Request(), new Response()),
            KernelEvents::TERMINATE,
        );
        $this->probe();

        self::assertSame(0, $this->computed, 'a write that changes no price display is not flushed');
    }

    private function read(?Customer $customer, string $path, string $value): string
    {
        return $this->readThrough($this->getService(ResourceCache::class), $customer, $path, $value);
    }

    private function readThrough(ResourceCache $cache, ?Customer $customer, string $path, string $value): string
    {
        $this->logoutCustomer();

        if (null !== $customer) {
            $this->loginAsCustomerInSession($customer);
        }

        // One request is over, another begins: what a request memoizes dies with it.
        $this->forgetTheRequest();

        $answer = $cache->remember('catalog-scope-key', $path, static fn (): array => [$value]);
        self::assertIsArray($answer);

        return $answer[0];
    }

    /**
     * @param list<ProductVisibilityRuleInterface> $rules the rules of the modules: the restriction rule is always given
     */
    private function cacheOver(array $rules, ?ClockInterface $clock = null): ResourceCache
    {
        $customers = $this->getService(CurrentCustomerProvider::class);

        return new ResourceCache(
            new ArrayAdapter(0, true, 0, 0, $clock),
            true,
            60,
            ['/api/front/products', '/api/front/brands', '/api/front/categories'],
            ['/api/front/products'],
            $this->getService(PricingActivityChecker::class),
            $this->getService(CatalogActivityProbe::class),
            $this->getService(CustomerGroupResolver::class),
            productVisibility: new ProductVisibility(new ReservedSaleProductRule($this->getService(SaleAudienceChecker::class), $customers), $customers, $rules, catalogRestrictionRule: $this->getService(CatalogRestrictionRule::class)),
            categoryVisibility: $this->getService(CategoryVisibility::class),
            currentCustomerProvider: $customers,
            catalogScopeProbe: $this->getService(CatalogScopeProbe::class),
        );
    }

    private function restrictGroup(CustomerGroup $group): CatalogRestriction
    {
        $rule = (new CatalogRestriction())->setCustomerGroupId($group->getId())->setProductId($this->product->getId())->setMode('deny');
        $rule->save($this->getPropelConnection());

        return $rule;
    }

    private function restrictCustomer(Customer $customer): void
    {
        (new CatalogRestriction())->setCustomerId($customer->getId())->setProductId($this->product->getId())->setMode('deny')->save($this->getPropelConnection());
    }

    private function priceList(): PriceList
    {
        $list = (new PriceList())
            ->setCode('list-'.uniqid())
            ->setCurrencyId(Currency::getDefaultCurrency()->getId())
            ->setPriority(10)
            ->setExclusive(0)
            ->setActive(1);
        $list->save($this->getPropelConnection());

        return $list;
    }

    private function assignPriceList(PriceList $list, ?CustomerGroup $group = null, ?Customer $customer = null): PriceListAssignment
    {
        $assignment = (new PriceListAssignment())
            ->setPriceListId($list->getId())
            ->setCustomerGroupId($group?->getId())
            ->setCustomerId($customer?->getId());
        $assignment->save($this->getPropelConnection());

        return $assignment;
    }

    private function member(CustomerGroup $group): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);
    }

    private function forgetTheRequest(): void
    {
        $this->getService('services_resetter')->reset();
        $this->resetPriceMemos();
    }

    private int $computed = 0;

    private function warm(): void
    {
        $this->probe();
        $this->computed = 0;

        $this->probe();
        self::assertSame(0, $this->computed, 'the entry is in the cache: the read is served from it');
    }

    private function assertComputedAgain(string $context): void
    {
        static::getContainer()->get(EventDispatcherInterface::class)->dispatch(
            new TerminateEvent(static::getContainer()->get('kernel'), new Request(), new Response()),
            KernelEvents::TERMINATE,
        );
        $this->probe();

        self::assertSame(1, $this->computed, 'the read after the write is computed again: '.$context);
        $this->computed = 0;
    }

    private function probe(): void
    {
        $this->getService(ResourceCache::class)->remember('invalidation-key', '/api/front/countries', function (): array {
            ++$this->computed;

            return ['payload'];
        });
    }
}
