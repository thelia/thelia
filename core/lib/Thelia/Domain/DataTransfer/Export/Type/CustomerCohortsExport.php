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
 * The cohort table as the back-office screen shows it: a line per month of first order, a pair of
 * columns per month elapsed since, the customers of the cohort who ordered in it and their share
 * of the cohort. A month that has not come yet is empty.
 */
class CustomerCohortsExport extends AbstractCustomerReportExport
{
    public const FILE_NAME = 'customer_cohorts';

    /**
     * @return list<array<string, string|int|float|null>>
     */
    protected function getData(): array|string|ModelCriteria
    {
        $table = $this->lifetimeCalculator()->cohorts($this->criteria(), $this->granularity());

        if ([] === $table->rows) {
            throw self::noData();
        }

        $columns = ['cohort' => $this->label('Cohort'), 'customers' => $this->label('Customers')];

        for ($elapsed = 0; $elapsed < $table->columnCount; ++$elapsed) {
            $columns['elapsed_'.$elapsed] = $this->elapsedLabel($elapsed);
            $columns['elapsed_'.$elapsed.'_share'] = $this->elapsedLabel($elapsed).' (%)';
        }

        $this->orderAndAliases = $columns;
        $rows = [];

        foreach ($table->rows as $cohort) {
            $row = ['cohort' => $cohort->label, 'customers' => $cohort->size];

            foreach ($cohort->cells as $elapsed => $cell) {
                $row['elapsed_'.$elapsed] = $cell?->activeCustomers;
                $row['elapsed_'.$elapsed.'_share'] = null === $cell ? null : self::percent($cell->share);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The customers the cohorts gather and how many of them ordered more than once.
     *
     * @return list<ReportSummaryLine>
     */
    public function summarize(): array
    {
        $summary = $this->lifetimeCalculator()->lifetime($this->criteria());

        return [
            new ReportSummaryLine($this->label('New customers'), (string) $summary->customerCount),
            new ReportSummaryLine($this->label('Repeat rate'), number_format(self::percent($summary->repeatRate), 2, (string) $this->language->getDecimalSeparator(), '').' %'),
        ];
    }
}
