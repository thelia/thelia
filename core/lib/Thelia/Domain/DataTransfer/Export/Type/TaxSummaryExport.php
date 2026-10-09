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
use Thelia\Domain\Accounting\AccountingChart;
use Thelia\Domain\Accounting\AccountingEntry;
use Thelia\Domain\Accounting\SalesJournal;
use Thelia\Domain\DataTransfer\Export\ArrayAbstractExport;
use Thelia\Domain\DataTransfer\Export\ExportReport;
use Thelia\Domain\DataTransfer\Export\ReportingExportInterface;
use Thelia\Model\Lang;

/**
 * The taxes of the invoices of a period, one row per month of invoice and per rate: the
 * amount taxed, the tax and the number of pieces. Read from the pieces of the sales
 * journal, so that both say the same thing to the cent.
 */
class TaxSummaryExport extends ArrayAbstractExport implements ReportingExportInterface
{
    public const FILE_NAME = 'tax_summary';
    public const USE_RANGE_DATE = true;

    /** @var array<string, string> */
    protected array $orderAndAliases = [
        'period' => 'period',
        'rate' => 'rate',
        'taxable_amount' => 'taxable_amount',
        'tax_amount' => 'tax_amount',
        'pieces' => 'pieces',
    ];

    private ExportReport $report;

    public function __construct()
    {
        $this->report = new ExportReport();
    }

    public function report(): ExportReport
    {
        return $this->report;
    }

    /**
     * @return list<array<string, string|int>>
     */
    protected function getData(): array|string|ModelCriteria
    {
        $locale = isset($this->language) ? (string) $this->language->getLocale() : (string) Lang::getDefaultLanguage()->getLocale();
        $pieces = (new SalesJournal())->pieces(AccountingChart::fromSettings(), $this->rangeDate['start'] ?? null, $this->rangeDate['end'] ?? null, $this->report, $locale);
        $totals = [];

        foreach ($pieces as $piece) {
            $period = $piece->invoiceDate->format('Y-m');

            foreach ($piece->entries as $entry) {
                if (null === $entry->rateKey) {
                    continue;
                }

                $key = $period.'|'.$entry->rateKey;
                $totals[$key] ??= ['period' => $period, 'rate' => $entry->rateKey, 'taxable' => 0, 'tax' => 0, 'pieces' => []];

                if (AccountingEntry::ROLE_TAX === $entry->role) {
                    $totals[$key]['tax'] += $entry->creditCents;
                } else {
                    $totals[$key]['taxable'] += $entry->creditCents;
                }

                $totals[$key]['pieces'][$piece->invoiceRef] = true;
            }
        }

        // By month, then the highest rate first, compared as numbers.
        uasort($totals, static fn (array $left, array $right): int => [$left['period'], (float) $right['rate']] <=> [$right['period'], (float) $left['rate']]);

        return array_values(array_map(static fn (array $total): array => [
            'period' => $total['period'],
            'rate' => $total['rate'],
            'taxable_amount' => SalesJournal::amount($total['taxable']),
            'tax_amount' => SalesJournal::amount($total['tax']),
            'pieces' => \count($total['pieces']),
        ], $totals));
    }
}
