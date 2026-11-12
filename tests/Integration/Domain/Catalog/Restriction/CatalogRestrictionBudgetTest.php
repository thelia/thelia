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

namespace Thelia\Tests\Integration\Domain\Catalog\Restriction;

use Thelia\Domain\Catalog\Category\CategoryVisibility;
use Thelia\Domain\Catalog\Product\ProductVisibility;
use Thelia\Domain\Catalog\Restriction\CatalogRestrictionResolver;
use Thelia\Domain\Catalog\Restriction\CatalogScopeProbe;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\ProductQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A shop with no catalog restriction pays nothing for the feature: whatever the front asks
 * (the rule, the categories, the product query), the table is only named by the existence
 * statement the front runs anyway.
 */
final class CatalogRestrictionBudgetTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    public function testAShopWithNoRestrictionNeverReadsTheTable(): void
    {
        $this->getService('services_resetter')->reset();
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());

        $statements = $this->recordSqlQueries(function () use ($customer): void {
            $this->getService(CatalogRestrictionResolver::class)->scopeFor($customer);
            $this->getService(CatalogRestrictionResolver::class)->closedCategoryIdsFor($customer);
            $this->getService(CategoryVisibility::class)->closedCategoryIdsFor($customer);

            $query = ProductQuery::create();
            $this->getService(ProductVisibility::class)->applyTo($query, ProductTableMap::COL_ID);
            $query->limit(5)->find();
        });

        $readingTheRules = array_values(array_filter(
            $statements,
            static fn (string $statement): bool => !str_contains($statement, 'AS catalog_restrictions'),
        ));

        self::assertSame(0, self::countSqlQueriesSelectingFrom($readingTheRules, 'catalog_restriction'));
        self::assertSame(0, self::countSqlQueriesSelectingFrom($readingTheRules, 'category'), 'the category tree is read only for a rule that targets a category');
    }

    public function testTheScopeProbeOfTheCacheReadsNothingInAShopWithNeitherARestrictionNorAPriceList(): void
    {
        $this->getService('services_resetter')->reset();
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());

        $statements = $this->recordSqlQueries(function () use ($customer): void {
            $probe = $this->getService(CatalogScopeProbe::class);
            self::assertFalse($probe->anyGroupIsRestricted());
            self::assertFalse($probe->anyGroupHasPriceList());
            self::assertFalse($probe->customerIsScopedAlone($customer));
            self::assertFalse($probe->customerHasRestrictionAlone($customer));
        });

        $beyondTheExistenceProbe = array_values(array_filter(
            $statements,
            static fn (string $statement): bool => !str_contains($statement, 'AS catalog_restrictions'),
        ));

        self::assertSame(0, self::countSqlQueriesSelectingFrom($beyondTheExistenceProbe, 'catalog_restriction'));
        self::assertSame(0, self::countSqlQueriesSelectingFrom($beyondTheExistenceProbe, 'price_list_assignment'));
    }
}
