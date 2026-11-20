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

namespace Thelia\Domain\Report\Customer;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Order\Sql\OrderAmountSql;
use Thelia\Domain\Report\ReportQuery;
use Thelia\Domain\Report\Sales\SalesReportCriteria;
use Thelia\Model\OrderStatusQuery;

/**
 * What customers bring over their life with the shop: the cohort table (the customers of a month
 * of first order, and how many of them ordered again in each month after), their lifetime value,
 * and the new, returning and dormant customers of a period. The back-office screen and the
 * exports both read it.
 *
 * A customer belongs to the cohort of their first order, not of their sign-up: an account that
 * never ordered is in no cohort. The first order is looked for in the whole history of the shop,
 * among the orders of the criteria (their status scope and their sale units): a customer who
 * ordered before the period is not new in it. Only the orders created up to the end of the
 * criteria are read.
 *
 * A cancelled or refunded order (a status of the shop answering for one of them included) is left
 * out in both status scopes: the customer reports count what customers spent, as the customer
 * list does, where the sales reports of every order count it.
 *
 * Each order is totalled as it was charged (goods with taxes, less the discount, plus the
 * postage) by the rounding rule of its order (OrderAmountSql), as the sales reports and the
 * dashboard total it, and the amounts are summed whatever the currency of the order. The
 * figures are revenue, not margin: the cost of the goods is not kept with the order.
 */
final readonly class CustomerLifetimeCalculator
{
    public function __construct(private ?ConnectionInterface $connection = null)
    {
    }

    /**
     * The cohorts of the customers whose first order was created between the start and the end of
     * the criteria, oldest first, followed up to the end of the criteria.
     */
    public function cohorts(SalesReportCriteria $criteria, CohortGranularity $granularity): CohortTable
    {
        $firstIndex = $granularity->indexOf($criteria->from);
        $lastIndex = $granularity->indexOf($criteria->to);
        $columnCount = $lastIndex - $firstIndex + 1;

        $sql = 'WITH '.$this->placedOrderSql($criteria).',
            first_order AS (
                SELECT placed_order.customer_id, MIN(placed_order.created_at) AS first_at
                FROM placed_order
                GROUP BY placed_order.customer_id
                HAVING MIN(placed_order.created_at) >= :from
            )
            SELECT
                '.$granularity->indexSql('first_order.first_at').' AS cohort_index,
                '.$granularity->indexSql('placed_order.created_at').' - '.$granularity->indexSql('first_order.first_at').' AS elapsed,
                COUNT(DISTINCT placed_order.customer_id) AS active_customers,
                CAST(SUM(placed_order.order_total) AS DECIMAL(16,6)) AS revenue
            FROM placed_order
            INNER JOIN first_order ON first_order.customer_id = placed_order.customer_id
            GROUP BY cohort_index, elapsed
            ORDER BY cohort_index, elapsed';

        /** @var array<int, array<int, array{customers: int, revenue: float}>> $figures */
        $figures = [];

        foreach ($this->fetchAll($sql, $criteria) as $row) {
            $figures[(int) $row['cohort_index']][(int) $row['elapsed']] = [
                'customers' => (int) $row['active_customers'],
                'revenue' => (float) $row['revenue'],
            ];
        }

        $rows = [];

        foreach ($figures as $cohortIndex => $byElapsed) {
            // Column 0 holds every customer of the cohort, by construction: it is the month of their first order.
            $size = $byElapsed[0]['customers'] ?? 0;
            $cells = [];
            $cumulative = 0.0;

            for ($elapsed = 0; $elapsed < $columnCount; ++$elapsed) {
                if ($cohortIndex + $elapsed > $lastIndex) {
                    $cells[] = null;

                    continue;
                }

                $customers = $byElapsed[$elapsed]['customers'] ?? 0;
                $revenue = $byElapsed[$elapsed]['revenue'] ?? 0.0;
                $cumulative += $revenue;

                $cells[] = new CohortCell(
                    $elapsed,
                    $customers,
                    ReportQuery::share($customers, $size),
                    $revenue,
                    $cumulative,
                    ReportQuery::share($cumulative, $size),
                );
            }

            $rows[] = new CohortRow($cohortIndex, $granularity->label($cohortIndex), $granularity->startOf($cohortIndex), $size, $cells);
        }

        return new CohortTable($granularity, $rows, max(0, $columnCount));
    }

    /**
     * The lifetime figures of the customers whose first order was created between the start and
     * the end of the criteria, from that order up to the end of the criteria.
     */
    public function lifetime(SalesReportCriteria $criteria): LifetimeSummary
    {
        $sql = 'WITH '.$this->placedOrderSql($criteria).',
            customer_life AS (
                SELECT
                    placed_order.customer_id,
                    MIN(placed_order.created_at) AS first_at,
                    MAX(placed_order.created_at) AS last_at,
                    COUNT(*) AS order_count,
                    SUM(placed_order.order_total) AS revenue
                FROM placed_order
                GROUP BY placed_order.customer_id
                HAVING MIN(placed_order.created_at) >= :from
            )
            SELECT
                COUNT(*) AS customer_count,
                COALESCE(SUM(customer_life.order_count), 0) AS order_count,
                CAST(COALESCE(SUM(customer_life.revenue), 0) AS DECIMAL(16,6)) AS revenue,
                COALESCE(AVG(TIMESTAMPDIFF(SECOND, customer_life.first_at, customer_life.last_at)), 0) / 86400 AS lifespan_days,
                COALESCE(SUM(customer_life.order_count > 1), 0) AS repeat_customers
            FROM customer_life';

        $row = $this->fetchAll($sql, $criteria)[0] ?? [];
        $customers = (int) ($row['customer_count'] ?? 0);
        $orders = (int) ($row['order_count'] ?? 0);
        $revenue = (float) ($row['revenue'] ?? 0);

        return new LifetimeSummary(
            $customers,
            $orders,
            $revenue,
            ReportQuery::share($revenue, $customers),
            round((float) ($row['lifespan_days'] ?? 0), 1),
            ReportQuery::share((int) ($row['repeat_customers'] ?? 0), $customers),
            ReportQuery::share($orders, $customers),
        );
    }

    /**
     * Every customer who ordered up to the end of the period, in one segment each: new and
     * returning customers ordered in the period, and share its revenue; the others did not, and
     * are dormant when their last order is older than the given number of days at the end of the
     * period, lapsing otherwise. Each segment also gives what its customers spent since their
     * first order, against what every customer spent up to the end of the period: both shares
     * add up to the whole.
     *
     * @return list<SegmentRow> new, returning, lapsing, dormant
     */
    public function segments(SalesReportCriteria $criteria, int $dormantAfterDays): array
    {
        if ($dormantAfterDays < 1) {
            throw new \InvalidArgumentException(\sprintf('A customer is dormant after one day without order at least, %d given.', $dormantAfterDays));
        }

        $dormantBefore = $criteria->to->modify(\sprintf('-%d days', $dormantAfterDays));

        // Each date is a parameter named once: the statement may be prepared without emulation.
        $sql = 'WITH '.$this->placedOrderSql($criteria, computedOnce: true).',
            bound AS (
                SELECT CAST(:from AS DATETIME) AS period_start, CAST(:dormant_before AS DATETIME) AS dormant_before
            ),
            customer_life AS (
                SELECT
                    placed_order.customer_id,
                    MIN(placed_order.created_at) AS first_at,
                    MAX(placed_order.created_at) AS last_at,
                    SUM(placed_order.order_total) AS lifetime_revenue,
                    SUM(CASE WHEN placed_order.created_at >= bound.period_start THEN placed_order.order_total ELSE 0 END) AS period_revenue,
                    SUM(placed_order.created_at >= bound.period_start) AS period_orders
                FROM placed_order
                CROSS JOIN bound
                GROUP BY placed_order.customer_id
            ),
            classified AS (
                SELECT
                    customer_life.*,
                    customer_life.period_orders > 0 AND customer_life.first_at >= bound.period_start AS is_new,
                    customer_life.period_orders > 0 AND customer_life.first_at < bound.period_start AS is_returning,
                    customer_life.period_orders = 0 AND customer_life.last_at >= bound.dormant_before AS is_lapsing,
                    customer_life.period_orders = 0 AND customer_life.last_at < bound.dormant_before AS is_dormant
                FROM customer_life
                CROSS JOIN bound
            )
            SELECT
                COALESCE(SUM(is_new), 0) AS new_customers,
                CAST(COALESCE(SUM(CASE WHEN is_new THEN period_revenue ELSE 0 END), 0) AS DECIMAL(16,6)) AS new_period,
                CAST(COALESCE(SUM(CASE WHEN is_new THEN lifetime_revenue ELSE 0 END), 0) AS DECIMAL(16,6)) AS new_lifetime,
                COALESCE(SUM(is_returning), 0) AS returning_customers,
                CAST(COALESCE(SUM(CASE WHEN is_returning THEN period_revenue ELSE 0 END), 0) AS DECIMAL(16,6)) AS returning_period,
                CAST(COALESCE(SUM(CASE WHEN is_returning THEN lifetime_revenue ELSE 0 END), 0) AS DECIMAL(16,6)) AS returning_lifetime,
                COALESCE(SUM(is_lapsing), 0) AS lapsing_customers,
                CAST(COALESCE(SUM(CASE WHEN is_lapsing THEN period_revenue ELSE 0 END), 0) AS DECIMAL(16,6)) AS lapsing_period,
                CAST(COALESCE(SUM(CASE WHEN is_lapsing THEN lifetime_revenue ELSE 0 END), 0) AS DECIMAL(16,6)) AS lapsing_lifetime,
                COALESCE(SUM(is_dormant), 0) AS dormant_customers,
                CAST(COALESCE(SUM(CASE WHEN is_dormant THEN period_revenue ELSE 0 END), 0) AS DECIMAL(16,6)) AS dormant_period,
                CAST(COALESCE(SUM(CASE WHEN is_dormant THEN lifetime_revenue ELSE 0 END), 0) AS DECIMAL(16,6)) AS dormant_lifetime,
                CAST(COALESCE(SUM(period_revenue), 0) AS DECIMAL(16,6)) AS period_total,
                CAST(COALESCE(SUM(lifetime_revenue), 0) AS DECIMAL(16,6)) AS lifetime_total
            FROM classified';

        $row = $this->fetchAll($sql, $criteria, ['dormant_before' => ReportQuery::sqlDate($dormantBefore)])[0] ?? [];
        $periodTotal = (float) ($row['period_total'] ?? 0);
        $lifetimeTotal = (float) ($row['lifetime_total'] ?? 0);

        $segments = [];

        foreach (CustomerSegment::cases() as $segment) {
            $prefix = $segment->value;
            $periodRevenue = (float) ($row[$prefix.'_period'] ?? 0);
            $lifetimeRevenue = (float) ($row[$prefix.'_lifetime'] ?? 0);

            $segments[] = new SegmentRow(
                $segment,
                (int) ($row[$prefix.'_customers'] ?? 0),
                $periodRevenue,
                ReportQuery::share($periodRevenue, $periodTotal),
                $lifetimeRevenue,
                ReportQuery::share($lifetimeRevenue, $lifetimeTotal),
            );
        }

        return $segments;
    }

    /**
     * Every order of the scope and the sale units of the criteria created up to its end, whatever
     * its start, cancelled and refunded orders left out, with what the customer was charged for
     * it. MariaDB merges the expression into each query that reads it: the amount is then only
     * computed for the orders a query keeps (the orders of the customers of the cohorts), five
     * times faster on five years of orders than a table of every order built beforehand.
     *
     * @param bool $computedOnce for a query that reads every order and its amount twice (the
     *                           segments): DISTINCT, over rows the order id makes unique, keeps the
     *                           expression a table of its own, and each amount computed once
     */
    private function placedOrderSql(SalesReportCriteria $criteria, bool $computedOnce = false): string
    {
        $condition = 'o.created_at <= :to'
            .ReportQuery::scopeCondition($criteria)
            .' AND NOT ('.ReportQuery::inList('o.status_id', [...OrderStatusQuery::getCanceledStatusIdList(), ...OrderStatusQuery::getRefundedStatusIdList()]).')';

        return 'placed_order AS (
                SELECT'.($computedOnce ? ' DISTINCT' : '').'
                    o.id,
                    o.customer_id,
                    o.created_at,
                    '.OrderAmountSql::orderTotal('o').' AS order_total
                FROM `order` AS o
                WHERE '.$condition.'
            )';
    }

    /**
     * @param array<string, scalar> $parameters
     *
     * @return list<array<string, mixed>>
     */
    private function fetchAll(string $sql, SalesReportCriteria $criteria, array $parameters = []): array
    {
        return (new ReportQuery($this->connection))->fetchAll($sql, $criteria, $parameters);
    }
}
