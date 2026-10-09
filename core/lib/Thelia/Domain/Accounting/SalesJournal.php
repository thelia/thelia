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

namespace Thelia\Domain\Accounting;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\DataTransfer\Export\ExportReport;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderQuery;

/**
 * The pieces of the invoices of a period, by their invoice date: the order is booked when
 * it is invoiced, not when it was placed. An order without invoice reference has no piece;
 * an order the chart of accounts cannot book is left out; both are said in the report.
 */
final class SalesJournal
{
    private const BATCH_SIZE = 200;

    /**
     * @return list<AccountingPiece>
     *
     * @throws InvalidAccountingChartException when the chart of accounts is missing an account
     */
    public function pieces(AccountingChart $chart, ?\DateTimeInterface $from, ?\DateTimeInterface $to, ExportReport $report, string $locale): array
    {
        $translator = Translator::getInstance();

        if ([] !== $missing = $chart->missing()) {
            $labels = [
                AccountingChart::MISSING_CUSTOMER_ACCOUNT => $translator->trans('the customer account', [], null, $locale),
                AccountingChart::MISSING_SHIPPING_ACCOUNT => $translator->trans('the shipping account', [], null, $locale),
                AccountingChart::MISSING_RATE_ACCOUNTS => $translator->trans('the accounts of the tax rates', [], null, $locale),
            ];

            throw new InvalidAccountingChartException($translator->trans('The chart of accounts is incomplete: set %missing in the accounting settings before exporting the sales journal.', ['%missing' => implode(', ', array_map(static fn (string $item): string => $labels[$item], $missing))], null, $locale));
        }

        $shopCurrency = CurrencyQuery::create()->findOneByByDefault(1) ?? throw new \RuntimeException('The shop has no default currency.');
        $builder = new SalesPieceBuilder();
        $pieces = [];
        $offset = 0;

        do {
            $orders = $this->invoiced($from, $to)->offset($offset)->limit(self::BATCH_SIZE)->find();

            foreach ($orders as $order) {
                try {
                    $pieces[] = $builder->build($order, $chart, $shopCurrency);
                } catch (OrderNotExportableException $exception) {
                    $report->addWarning($exception->getMessage());
                }
            }

            $offset += self::BATCH_SIZE;
            OrderTableMap::clearInstancePool();
        } while (self::BATCH_SIZE === \count($orders));

        $withoutReference = $this->period(OrderQuery::create(), $from, $to)
            ->where('(Order.InvoiceRef IS NULL OR Order.InvoiceRef = \'\')')
            ->count();

        if ($withoutReference > 0) {
            $report->addWarning($translator->trans('%count orders of the period have an invoice date but no invoice reference: they are not in the journal.', ['%count' => $withoutReference], null, $locale));
        }

        $report->addWarning(null !== ModuleQuery::create()->filterByCode('CreditNote')->filterByActivate(1)->findOne()
            ? $translator->trans('The credit notes of the CreditNote module are not in this journal: book them separately.', [], null, $locale)
            : $translator->trans('No credit note module is installed: refunds made without credit note are not in this journal.', [], null, $locale));

        return $pieces;
    }

    private function invoiced(?\DateTimeInterface $from, ?\DateTimeInterface $to): OrderQuery
    {
        return $this->period(OrderQuery::create(), $from, $to)
            ->where('Order.InvoiceRef IS NOT NULL')
            ->where('Order.InvoiceRef <> \'\'')
            ->orderByInvoiceDate()
            ->orderById();
    }

    private function period(OrderQuery $query, ?\DateTimeInterface $from, ?\DateTimeInterface $to): OrderQuery
    {
        $query->filterByInvoiceDate(null, Criteria::ISNOTNULL);

        $range = array_filter(['min' => $from, 'max' => $to]);

        return [] === $range ? $query : $query->filterByInvoiceDate($range);
    }

    /**
     * @param list<AccountingPiece> $pieces
     */
    public static function summarize(array $pieces, ExportReport $report, string $locale): void
    {
        $debit = 0;
        $credit = 0;

        foreach ($pieces as $piece) {
            foreach ($piece->entries as $entry) {
                $debit += $entry->debitCents;
                $credit += $entry->creditCents;
            }
        }

        $report->addLine(Translator::getInstance()->trans('%count pieces, debit %debit, credit %credit.', ['%count' => \count($pieces), '%debit' => self::amount($debit), '%credit' => self::amount($credit)], null, $locale));
    }

    public static function amount(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', \STR_PAD_LEFT);
    }
}
