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

namespace Thelia\Domain\DataTransfer\Export\Type;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Domain\DataTransfer\Export\ReportSummaryLine;
use Thelia\Domain\Report\Customer\CustomerDormancy;
use Thelia\Domain\Report\Customer\CustomerSegment;

/**
 * The new, returning, lapsing and dormant customers of the period, one segment each: how many they
 * are, what they spent in the period and its share of the revenue, what they spent since their
 * first order and its share. A customer whose last order is older than the number of days of the
 * shop setting customer_dormant_after_days at the end of the period is dormant, even if they ordered
 * earlier in the period; a customer without an order in the period is lapsing before that.
 */
class CustomerSegmentsExport extends AbstractCustomerReportExport
{
    public const FILE_NAME = 'customer_segments';

    /** @var array<string, string> */
    protected array $orderAndAliases = [
        'segment' => 'Segment',
        'customers' => 'Customers',
        'period_revenue' => 'Revenue of the period incl. tax',
        'period_share' => 'Share of the revenue of the period (%)',
        'lifetime_revenue' => 'Revenue since the first order incl. tax',
        'lifetime_share' => 'Share of the revenue since the first order (%)',
    ];

    /**
     * @return list<array<string, string|int|float|null>>
     */
    protected function getData(): array|string|ModelCriteria
    {
        $days = CustomerDormancy::days();
        $segments = $this->lifetimeCalculator()->segments($this->criteria(), $days);

        if (0 === array_sum(array_map(static fn ($row): int => $row->customerCount, $segments))) {
            throw self::noData();
        }

        $rows = [];

        foreach ($segments as $segment) {
            $rows[] = [
                'segment' => $this->segmentLabel($segment->segment, $days),
                'customers' => $segment->customerCount,
                'period_revenue' => self::amount($segment->periodRevenue),
                'period_share' => self::percent($segment->periodShare),
                'lifetime_revenue' => self::amount($segment->lifetimeRevenue),
                'lifetime_share' => self::percent($segment->lifetimeShare),
            ];
        }

        return $rows;
    }

    /**
     * The number of new, returning, lapsing and dormant customers.
     *
     * @return list<ReportSummaryLine>
     */
    public function summarize(): array
    {
        $days = CustomerDormancy::days();
        $lines = [];

        foreach ($this->lifetimeCalculator()->segments($this->criteria(), $days) as $segment) {
            $lines[] = new ReportSummaryLine($this->segmentLabel($segment->segment, $days), (string) $segment->customerCount);
        }

        return $lines;
    }

    private function segmentLabel(CustomerSegment $segment, int $days): string
    {
        return match ($segment) {
            CustomerSegment::New => $this->label('New customers'),
            CustomerSegment::Returning => $this->label('Returning customers'),
            CustomerSegment::Lapsing => $this->label('Lapsing customers (no order in the period, not dormant yet)'),
            CustomerSegment::Dormant => \Thelia\Core\Translation\Translator::getInstance()->trans('Dormant customers (no order for %days% days)', ['%days%' => $days], 'core', $this->locale()),
        };
    }
}
