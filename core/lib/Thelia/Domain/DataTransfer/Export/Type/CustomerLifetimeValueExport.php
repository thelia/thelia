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

/**
 * The lifetime value of each cohort: a line per month of first order, a column per month elapsed
 * since, what the cohort spent from its first order to the end of that month, per customer. A
 * month that has not come yet is empty. Revenue incl. tax, not margin.
 */
class CustomerLifetimeValueExport extends AbstractCustomerReportExport
{
    public const FILE_NAME = 'customer_lifetime_value';

    /**
     * @return list<array<string, string|int|float|null>>
     */
    protected function getData(): array|string|ModelCriteria
    {
        $table = $this->lifetimeCalculator()->cohorts($this->criteria(), $this->granularity());

        if ([] === $table->rows) {
            throw self::noData();
        }

        $columns = [
            'cohort' => $this->label('Cohort'),
            'customers' => $this->label('Customers'),
            'lifetime_value' => $this->label('Lifetime value incl. tax'),
        ];

        for ($elapsed = 0; $elapsed < $table->columnCount; ++$elapsed) {
            $columns['elapsed_'.$elapsed] = $this->elapsedLabel($elapsed);
        }

        $this->orderAndAliases = $columns;
        $rows = [];

        foreach ($table->rows as $cohort) {
            $row = ['cohort' => $cohort->label, 'customers' => $cohort->size, 'lifetime_value' => self::amount($cohort->lifetimeValue())];

            foreach ($cohort->cells as $elapsed => $cell) {
                $row['elapsed_'.$elapsed] = null === $cell ? null : self::amount($cell->cumulativeValuePerCustomer);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The average lifetime value, lifespan and repeat rate of the customers of the cohorts.
     *
     * @return list<ReportSummaryLine>
     */
    public function summarize(): array
    {
        $summary = $this->lifetimeCalculator()->lifetime($this->criteria());
        $separator = (string) $this->language->getDecimalSeparator();

        return [
            new ReportSummaryLine($this->label('Average lifetime value incl. tax'), $this->money($summary->averageLifetimeValue)),
            new ReportSummaryLine($this->label('Average lifespan (days)'), number_format($summary->averageLifespanDays, 1, $separator, '')),
            new ReportSummaryLine($this->label('Repeat rate'), number_format(self::percent($summary->repeatRate), 2, $separator, '').' %'),
        ];
    }
}
