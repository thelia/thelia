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

namespace Thelia\Tests\Integration\Domain\Pricing\Rule\Overview;

use Thelia\Domain\Pricing\Rule\Overview\CatalogPriceRuleOverviewQuery;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Model\CatalogPriceRule;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The back-office list: state, coverage and audience of every rule, in a fixed
 * number of statements.
 */
final class CatalogPriceRuleOverviewQueryTest extends ActionIntegrationTestCase
{
    use RecordsSqlQueries;

    public function testEveryRuleComesWithItsStateAndCounts(): void
    {
        $currency = $this->factory->currency();
        $category = $this->factory->category();
        $this->factory->product($category, $this->factory->taxRule(), $currency);
        $product = $this->factory->product($category, $this->factory->taxRule(), $currency);
        $this->factory->productSaleElement($product);
        $customer = $this->factory->customer($this->factory->customerTitle());
        $repricer = $this->getService(RuleRepricer::class);

        $running = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0, 'priority' => 10]);
        $this->factory->catalogPriceRuleCriterion($running, CatalogPriceRule::CRITERION_CATEGORY, $category->getId());
        $repricer->afterRuleChanged($running);

        $scheduled = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0, 'priority' => 20, 'startDate' => new \DateTime('+1 day'), 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS]);
        $this->factory->catalogPriceRuleCustomer($scheduled, $customer);
        $repricer->afterRuleChanged($scheduled);

        $expired = $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0, 'priority' => 30, 'endDate' => new \DateTime('-1 day')]);
        $off = $this->factory->catalogPriceRule(['active' => false, 'priority' => 40]);
        $this->factory->catalogPriceRuleCriterion($off, 'moon_phase', 1);

        $statements = [];
        $overview = [];
        $statements = $this->recordSqlQueries(function () use (&$overview, $running, $scheduled, $expired, $off): void {
            $overview = $this->getService(CatalogPriceRuleOverviewQuery::class)->overview([$running->getId(), $scheduled->getId(), $expired->getId(), $off->getId()]);
        });

        self::assertCount(4, $overview);
        self::assertSame([$running->getId(), $scheduled->getId(), $expired->getId(), $off->getId()], array_map(static fn ($line): int => (int) $line->rule->getId(), $overview));

        self::assertSame(CatalogPriceRule::STATE_RUNNING, $overview[0]->state);
        self::assertSame(2, $overview[0]->productCount);
        self::assertSame(3, $overview[0]->saleElementCount);

        self::assertSame(CatalogPriceRule::STATE_SCHEDULED, $overview[1]->state);
        self::assertSame(1, $overview[1]->customerCount);

        self::assertSame(CatalogPriceRule::STATE_EXPIRED, $overview[2]->state);
        self::assertSame(CatalogPriceRule::STATE_INACTIVE, $overview[3]->state);
        self::assertSame(['moon_phase'], $overview[3]->unknownCriterionTypes);

        self::assertLessThanOrEqual(4, \count(array_filter($statements, static fn (string $statement): bool => str_starts_with($statement, 'SELECT'))), 'a fixed number of statements, whatever the number of rules');
    }
}
