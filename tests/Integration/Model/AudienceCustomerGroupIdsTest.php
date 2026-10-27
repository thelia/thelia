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

namespace Thelia\Tests\Integration\Model;

use Thelia\Model\CatalogPriceRule;
use Thelia\Model\Sale;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * An operation and a price rule tell which customer groups they are reserved for in one
 * read: the screens and the services that need the list do not each carry their own query.
 */
final class AudienceCustomerGroupIdsTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    public function testASaleListsItsGroupsInIdOrderInOneQuery(): void
    {
        $factory = $this->createFixtureFactory();
        $first = $factory->customerGroup();
        $second = $factory->customerGroup();
        $sale = $factory->sale(['audienceMode' => Sale::AUDIENCE_MODE_CUSTOMER_GROUPS]);
        $factory->saleCustomerGroup($sale, $second);
        $factory->saleCustomerGroup($sale, $first);
        $factory->saleCustomerGroup($factory->sale(['audienceMode' => Sale::AUDIENCE_MODE_CUSTOMER_GROUPS]), $factory->customerGroup());
        $ids = [];

        $statements = $this->recordSqlQueries(static function () use ($sale, &$ids): void {
            $ids = $sale->getCustomerGroupIds();
        });

        self::assertSame([(int) $first->getId(), (int) $second->getId()], $ids);
        self::assertCount(1, $statements);
    }

    public function testARuleListsItsGroupsInIdOrderInOneQuery(): void
    {
        $factory = $this->createFixtureFactory();
        $first = $factory->customerGroup();
        $second = $factory->customerGroup();
        $rule = $factory->catalogPriceRule(['audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMER_GROUPS]);
        $factory->catalogPriceRuleCustomerGroup($rule, $second);
        $factory->catalogPriceRuleCustomerGroup($rule, $first);
        $ids = [];

        $statements = $this->recordSqlQueries(static function () use ($rule, &$ids): void {
            $ids = $rule->getCustomerGroupIds();
        });

        self::assertSame([(int) $first->getId(), (int) $second->getId()], $ids);
        self::assertCount(1, $statements);
    }

    public function testNothingIsListedForAnOperationWithoutGroupsOrNotSavedYet(): void
    {
        $sale = $this->createFixtureFactory()->sale();

        self::assertSame([], $sale->getCustomerGroupIds());
        self::assertSame([], (new Sale())->getCustomerGroupIds());
        self::assertSame([], (new CatalogPriceRule())->getCustomerGroupIds());
    }
}
