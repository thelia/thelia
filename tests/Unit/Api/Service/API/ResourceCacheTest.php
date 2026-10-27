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

namespace Thelia\Tests\Unit\Api\Service\API;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Thelia\Api\Service\API\ResourceCache;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Catalog\Product\ProductVisibilityRuleInterface;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivity;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Domain\Sale\ReservedSaleProductRule;
use Thelia\Domain\SaleUnit\Pricing\SaleUnitPricingActivityChecker;
use Thelia\Domain\SaleUnit\Stock\SaleUnitStockActivityChecker;
use Thelia\Model\Customer;
use Thelia\Tests\Support\Pricing\LoadedCustomerDiscountRate;

/**
 * The cross-request cache of the data access layer keys on the path, the format and the
 * locale, and on the customer group of the one asking where a price travels.
 *
 * A running reserved operation or a rule reserved for named customers breaks the promise
 * that the answer is the same for everybody for the customers they may price, not for the
 * visitors, who they never reach. A group that prices on its own splits the entries of the
 * price paths by group instead of stepping aside. The paths that carry no price keep their
 * cache through all of it.
 */
final class ResourceCacheTest extends TestCase
{
    private const CATALOG_PATH = '/api/front/products/1';
    private const NEUTRAL_PATH = '/api/front/countries';

    private const DEFAULT_GROUP = 1;

    private ?Customer $asking = null;

    public function testTheCatalogIsCachedWhenNothingPricesPerVisitor(): void
    {
        $cache = $this->cache();

        self::assertSame(['first'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['first']));
        self::assertSame(
            ['first'],
            $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['second']),
            'The second read is answered from the cache, so the payload is the first one.',
        );
    }

    public function testTheCatalogIsNotCachedForACustomerWhileAReservedOperationRuns(): void
    {
        $cache = $this->cache(activity: $this->activity(hasActiveReservedSale: true));
        $this->asking = $this->customer(10, group: 2);

        self::assertSame(['first'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['first']));
        self::assertSame(
            ['second'],
            $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['second']),
            'A reserved price would be served to whoever asked next: nothing is cached for the customer it may price.',
        );
    }

    public function testTheCatalogIsNotCachedForACustomerWhileARuleReservedForNamedCustomersRuns(): void
    {
        $cache = $this->cache(activity: $this->activity(hasActiveNamedRule: true));
        $this->asking = $this->customer(10, group: 2);

        self::assertSame(['first'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['first']));
        self::assertSame(['second'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['second']));
    }

    /**
     * A visitor with no account is never owed a reserved price: the entry of the default
     * group is theirs, whichever operation runs.
     */
    public function testAVisitorStaysOnTheCacheWhileAReservedOperationRuns(): void
    {
        $cache = $this->cache(activity: $this->activity(hasActiveReservedSale: true, hasActiveNamedRule: true));

        self::assertSame(['first'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['first']));
        self::assertSame(['first'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['second']));
    }

    public function testAGroupThatPricesOnItsOwnSplitsTheEntriesByGroup(): void
    {
        $cache = $this->cache(activity: $this->activity(hasCustomerGroupPrices: true));

        $read = function (?Customer $customer, string $payload) use ($cache): array {
            $this->asking = $customer;

            return $cache->remember('key', self::CATALOG_PATH, static fn (): array => [$payload]);
        };

        $groupA = $this->customer(10, group: 2);
        $groupB = $this->customer(11, group: 3);
        $otherInGroupA = $this->customer(12, group: 2);

        self::assertSame(['A'], $read($groupA, 'A'));
        self::assertSame(['B'], $read($groupB, 'B'), 'a customer of another group is never served the entry of the first');
        self::assertSame(['visitor'], $read(null, 'visitor'), 'nor is a visitor');
        self::assertSame(['A'], $read($groupA, 'again'), 'the same group is served from the cache');
        self::assertSame(['A'], $read($otherInGroupA, 'again'), 'a second customer of the group shares the entry');
        self::assertSame(['visitor'], $read(null, 'again'));
        self::assertSame(['B'], $read($groupB, 'again'));
    }

    public function testAGroupDiscountSplitsTheEntriesToo(): void
    {
        $cache = $this->cache(activity: $this->activity(hasDiscountedCustomerGroup: true));

        $this->asking = $this->customer(10, group: 2);
        self::assertSame(['A'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['A']));
        $this->asking = null;
        self::assertSame(['visitor'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['visitor']));
    }

    public function testACustomerWithADiscountOfTheirOwnIsNotCachedButTheirGroupIs(): void
    {
        $cache = $this->cache(activity: $this->activity());

        $this->asking = $this->customer(10, group: 2, discount: '10.000000');
        self::assertSame(['first'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['first']));
        self::assertSame(['second'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['second']), 'what a discounted customer is charged is theirs alone');

        $this->asking = null;
        self::assertSame(['visitor'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['visitor']));
        self::assertSame(['visitor'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['again']), 'the visitor stays on the cache');
    }

    /**
     * A module rule narrows the catalog per visitor whether or not it hides anything
     * from the visitor at hand: the catalog paths step aside while it is declared,
     * and the other paths keep their cache.
     */
    public function testTheCatalogIsBypassedWhileAModuleDeclaresAVisibilityRule(): void
    {
        $rule = $this->createStub(ProductVisibilityRuleInterface::class);
        $cache = $this->cache(productVisibilityRules: [$rule]);

        self::assertSame(['first'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['first']));
        self::assertSame(['second'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['second']));
        self::assertSame(['first'], $cache->remember('key', self::NEUTRAL_PATH, static fn (): array => ['first']));
        self::assertSame(['first'], $cache->remember('key', self::NEUTRAL_PATH, static fn (): array => ['second']));
    }

    /**
     * The bypass and the group are aimed at the two paths a price travels on. Everything
     * else the allow list holds answers the same thing to everybody, and keeps its cache.
     */
    public function testTheOtherCachedPathsAreUntouchedByWhatPricesPerGroupOrPerCustomer(): void
    {
        $cache = $this->cache(activity: $this->activity(hasActiveReservedSale: true, hasCustomerGroupPrices: true));
        $this->asking = $this->customer(10, group: 2, discount: '10.000000');

        self::assertSame(['first'], $cache->remember('key', self::NEUTRAL_PATH, static fn (): array => ['first']));
        self::assertSame(['first'], $cache->remember('key', self::NEUTRAL_PATH, static fn (): array => ['second']));

        $this->asking = $this->customer(11, group: 3);
        self::assertSame(['first'], $cache->remember('key', self::NEUTRAL_PATH, static fn (): array => ['third']), 'no group in the key of a path that carries no price');
    }

    public function testAPathOutsideTheAllowListIsNeverCached(): void
    {
        $cache = $this->cache();

        self::assertSame(['first'], $cache->remember('key', '/api/front/carts/1', static fn (): array => ['first']));
        self::assertSame(['second'], $cache->remember('key', '/api/front/carts/1', static fn (): array => ['second']));
    }

    /**
     * The key carries no sale unit: a price or a stock one unit owns, cached while
     * selling from it, would be served to a request selling from another.
     */
    public function testTheCatalogIsBypassedWhileASaleUnitPricesAnythingOfItsOwn(): void
    {
        $cache = $this->saleUnitCache(unitPrices: true, unitStocks: false);

        self::assertSame(['first'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['first']));
        self::assertSame(['second'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['second']));
        self::assertSame(['first'], $cache->remember('key', self::NEUTRAL_PATH, static fn (): array => ['first']));
        self::assertSame(['first'], $cache->remember('key', self::NEUTRAL_PATH, static fn (): array => ['second']), 'The paths no unit changes keep their cache.');
    }

    public function testTheCatalogIsBypassedWhileASaleUnitKeepsAStockOfItsOwn(): void
    {
        $cache = $this->saleUnitCache(unitPrices: false, unitStocks: true);

        self::assertSame(['first'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['first']));
        self::assertSame(['second'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['second']));
    }

    public function testTheCatalogIsCachedWhenNoSaleUnitOwnsAPriceOrAStock(): void
    {
        $cache = $this->saleUnitCache(unitPrices: false, unitStocks: false);

        self::assertSame(['first'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['first']));
        self::assertSame(['first'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['second']));
    }

    public function testTheCatalogIsCachedWhenNoProductVisibilityIsWired(): void
    {
        $cache = new ResourceCache(
            new ArrayAdapter(),
            enabled: true,
            ttl: 60,
            allowedPrefixes: ['/api/front/products'],
            visitorDependentPricePrefixes: ['/api/front/products'],
            pricingActivityChecker: $this->checker($this->activity()),
            catalogActivityProbe: $this->probe($this->activity()),
            customerGroupResolver: $this->groupResolver(),
        );

        self::assertSame(['first'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['first']));
        self::assertSame(['first'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['second']));
    }

    public function testNothingIsAskedAboutPricesWhenTheCacheIsOff(): void
    {
        $pricingActivityChecker = $this->createMock(PricingActivityChecker::class);
        $pricingActivityChecker->expects(self::never())->method('pricesDependOnWhoIsAsking');

        $saleUnitPricingActivityChecker = $this->createMock(SaleUnitPricingActivityChecker::class);
        $saleUnitPricingActivityChecker->expects(self::never())->method('anySaleUnitPricesAnything');

        $cache = new ResourceCache(
            new ArrayAdapter(),
            enabled: false,
            ttl: 60,
            allowedPrefixes: ['/api/front/products'],
            visitorDependentPricePrefixes: ['/api/front/products'],
            pricingActivityChecker: $pricingActivityChecker,
            catalogActivityProbe: $this->probe($this->activity()),
            customerGroupResolver: $this->groupResolver(),
            saleUnitPricingActivityChecker: $saleUnitPricingActivityChecker,
        );

        self::assertSame(['computed'], $cache->remember('key', self::CATALOG_PATH, static fn (): array => ['computed']));
    }

    /**
     * @param list<ProductVisibilityRuleInterface> $productVisibilityRules
     */
    private function cache(?CatalogActivity $activity = null, array $productVisibilityRules = []): ResourceCache
    {
        $activity ??= $this->activity();

        // No sale unit prices anything in these cases: the cache eligibility under
        // test is the one the groups, the rules and the operations decide, kept isolated
        // from the sale-unit bypass, which has its own coverage.
        $saleUnitPricingActivityChecker = $this->createMock(SaleUnitPricingActivityChecker::class);
        $saleUnitPricingActivityChecker->method('anySaleUnitPricesAnything')->willReturn(false);

        return new ResourceCache(
            new ArrayAdapter(),
            enabled: true,
            ttl: 60,
            allowedPrefixes: ['/api/front/products', '/api/front/product_sale_elements', '/api/front/countries'],
            visitorDependentPricePrefixes: ['/api/front/products', '/api/front/product_sale_elements'],
            pricingActivityChecker: $this->checker($activity),
            catalogActivityProbe: $this->probe($activity),
            customerGroupResolver: $this->groupResolver(),
            productVisibility: new ProductVisibility(
                $this->createStub(ReservedSaleProductRule::class),
                $this->createStub(CurrentCustomerProvider::class),
                $productVisibilityRules,
            ),
            saleUnitPricingActivityChecker: $saleUnitPricingActivityChecker,
            currentCustomerProvider: $this->currentCustomerProvider(),
        );
    }

    private function saleUnitCache(bool $unitPrices, bool $unitStocks): ResourceCache
    {
        $saleUnitPricingActivityChecker = $this->createStub(SaleUnitPricingActivityChecker::class);
        $saleUnitPricingActivityChecker->method('anySaleUnitPricesAnything')->willReturn($unitPrices);

        $saleUnitStockActivityChecker = $this->createStub(SaleUnitStockActivityChecker::class);
        $saleUnitStockActivityChecker->method('anySaleUnitKeepsItsOwnStock')->willReturn($unitStocks);

        return new ResourceCache(
            new ArrayAdapter(),
            enabled: true,
            ttl: 60,
            allowedPrefixes: ['/api/front/products', '/api/front/countries'],
            visitorDependentPricePrefixes: ['/api/front/products'],
            pricingActivityChecker: $this->checker($this->activity()),
            catalogActivityProbe: $this->probe($this->activity()),
            customerGroupResolver: $this->groupResolver(),
            saleUnitPricingActivityChecker: $saleUnitPricingActivityChecker,
            saleUnitStockActivityChecker: $saleUnitStockActivityChecker,
        );
    }

    private function activity(
        bool $hasActiveNamedRule = false,
        bool $hasActiveReservedSale = false,
        bool $hasCustomerGroupPrices = false,
        bool $hasDiscountedCustomerGroup = false,
    ): CatalogActivity {
        return new CatalogActivity(
            hasActivePublicRule: false,
            hasActiveNamedRule: $hasActiveNamedRule,
            hasActiveReservedSale: $hasActiveReservedSale,
            hasCustomerGroupPrices: $hasCustomerGroupPrices,
            hasDiscountedCustomerGroup: $hasDiscountedCustomerGroup,
            hasCategoryRestrictions: false,
            defaultCustomerGroupId: self::DEFAULT_GROUP,
        );
    }

    private function probe(CatalogActivity $activity): CatalogActivityProbe
    {
        $probe = $this->createStub(CatalogActivityProbe::class);
        $probe->method('read')->willReturn($activity);

        return $probe;
    }

    private function checker(CatalogActivity $activity): PricingActivityChecker
    {
        return new PricingActivityChecker($this->probe($activity), new LoadedCustomerDiscountRate());
    }

    private function groupResolver(): CustomerGroupResolver
    {
        $resolver = $this->createStub(CustomerGroupResolver::class);
        $resolver->method('groupIdOf')->willReturnCallback(
            static fn (?Customer $customer): int => null === $customer ? self::DEFAULT_GROUP : (int) $customer->getCustomerGroupId(),
        );

        return $resolver;
    }

    private function currentCustomerProvider(): CurrentCustomerProvider
    {
        $provider = $this->createStub(CurrentCustomerProvider::class);
        $provider->method('getCurrentCustomer')->willReturnCallback(fn (): ?Customer => $this->asking);

        return $provider;
    }

    private function customer(int $id, int $group, string $discount = '0.000000'): Customer
    {
        return (new Customer())->setId($id)->setCustomerGroupId($group)->setDiscount($discount);
    }
}
