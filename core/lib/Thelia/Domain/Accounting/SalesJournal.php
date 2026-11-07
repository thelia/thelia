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
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Map\OrderAddressTableMap;
use Thelia\Model\Map\OrderPostageTaxTableMap;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderProductTaxTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order;
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
     * The pieces one by one, read by batches: a year of invoices is never held at once.
     * What was left out is added to the report once the last piece is given.
     *
     * @return \Generator<int, AccountingPiece>
     *
     * @throws InvalidAccountingChartException when the chart of accounts is missing an account
     */
    public function pieces(AccountingChart $chart, ?\DateTimeInterface $from, ?\DateTimeInterface $to, ExportReport $report, string $locale): \Generator
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
        $creditNotes = new CreditNoteReader();
        $withCreditNotes = $creditNotes->isModuleActive() && $creditNotes->hasItsTables();
        $count = 0;
        $creditNoteCount = 0;
        $debit = 0;
        $credit = 0;

        $pieces = self::inDateOrder(
            $this->invoicePieces($chart, $from, $to, $report, $shopCurrency, $locale),
            $withCreditNotes ? $this->creditNotePieces($creditNotes, $chart, $from, $to, $report, $shopCurrency, $locale) : new \EmptyIterator(),
        );

        foreach ($pieces as $piece) {
            ++$count;

            if ($piece->isCreditNote()) {
                ++$creditNoteCount;
            }

            foreach ($piece->notes as $note) {
                $report->addWarning($note);
            }

            foreach ($piece->entries as $entry) {
                $debit += $entry->debitCents;
                $credit += $entry->creditCents;
            }

            yield $piece;
        }

        $report->addLine($translator->trans('%count pieces, debit %debit, credit %credit.', ['%count' => $count, '%debit' => self::amount($debit), '%credit' => self::amount($credit)], null, $locale));

        if ($withCreditNotes) {
            $report->addLine($translator->trans('%count credit notes among them.', ['%count' => $creditNoteCount], null, $locale));
        }

        $withoutReference = $this->period(OrderQuery::create(), $from, $to)
            ->where('(Order.InvoiceRef IS NULL OR Order.InvoiceRef = \'\')')
            ->count();

        if ($withoutReference > 0) {
            $report->addWarning($translator->trans('%count orders of the period have an invoice date but no invoice reference: they are not in the journal.', ['%count' => $withoutReference], null, $locale));
        }

        if (!$withCreditNotes) {
            $report->addWarning($creditNotes->isModuleActive()
                ? $translator->trans('The CreditNote module is active but its tables cannot be read: its credit notes are not in this journal, book them separately.', [], null, $locale)
                : $translator->trans('No credit note module is installed: refunds made without credit note are not in this journal.', [], null, $locale));
        }
    }

    /**
     * Each batch starts after the last order of the previous one, by invoice date then id: an
     * order invoiced while the export runs cannot shift the batches, and none is read twice.
     * The customer, the invoice address and the currency come with the order; the lines,
     * their taxes and the postage shares, with the batch.
     *
     * @return \Generator<int, AccountingPiece> by invoice date, then id
     */
    private function invoicePieces(AccountingChart $chart, ?\DateTimeInterface $from, ?\DateTimeInterface $to, ExportReport $report, Currency $shopCurrency, string $locale): \Generator
    {
        $builder = new SalesPieceBuilder();
        $after = null;

        do {
            $orders = iterator_to_array($this->invoicedBatch($from, $to, $after), false);
            $frozen = FrozenOrderAmounts::ofOrders(array_map(static fn (Order $order): int => (int) $order->getId(), $orders));

            foreach ($orders as $order) {
                try {
                    yield $builder->build($order, $chart, $shopCurrency, $locale, $frozen[(int) $order->getId()]);
                } catch (OrderNotExportableException $exception) {
                    $report->addWarning($exception->getMessage());
                }
            }

            $last = end($orders);
            $after = false === $last ? null : [$last->getInvoiceDate()?->format('Y-m-d H:i:s'), (int) $last->getId()];
            self::forgetTheBatch();
        } while (self::BATCH_SIZE === \count($orders));
    }

    /**
     * @return \Generator<int, AccountingPiece> by invoice date, then id
     */
    private function creditNotePieces(CreditNoteReader $reader, AccountingChart $chart, ?\DateTimeInterface $from, ?\DateTimeInterface $to, ExportReport $report, Currency $shopCurrency, string $locale): \Generator
    {
        $builder = new CreditPieceBuilder();

        foreach ($reader->invoiced($from, $to) as $creditNote) {
            try {
                yield $builder->build($creditNote, $chart, $shopCurrency, $locale);
            } catch (OrderNotExportableException $exception) {
                $report->addWarning($exception->getMessage());
            }
        }
    }

    /**
     * Two streams of pieces, each in date order, merged so that the journal stays in date
     * order: on the same second, the invoice before the credit note.
     *
     * @param \Iterator<int, AccountingPiece> $invoices
     * @param \Iterator<int, AccountingPiece> $creditNotes
     *
     * @return \Generator<int, AccountingPiece>
     */
    private static function inDateOrder(\Iterator $invoices, \Iterator $creditNotes): \Generator
    {
        $invoices->rewind();
        $creditNotes->rewind();

        while ($invoices->valid() || $creditNotes->valid()) {
            if (!$creditNotes->valid() || ($invoices->valid() && self::sortKey($invoices->current()) <= self::sortKey($creditNotes->current()))) {
                yield $invoices->current();
                $invoices->next();
            } else {
                yield $creditNotes->current();
                $creditNotes->next();
            }
        }
    }

    /**
     * @return array{string, int, int}
     */
    private static function sortKey(AccountingPiece $piece): array
    {
        return [$piece->invoiceDate->format('Y-m-d H:i:s'), $piece->isCreditNote() ? 1 : 0, $piece->sourceId];
    }

    /**
     * The models a batch read are let go: held by Propel's pools, a year of invoices would
     * be kept in memory to the end of the run.
     */
    private static function forgetTheBatch(): void
    {
        OrderTableMap::clearInstancePool();
        OrderProductTableMap::clearInstancePool();
        OrderProductTaxTableMap::clearInstancePool();
        OrderPostageTaxTableMap::clearInstancePool();
        OrderAddressTableMap::clearInstancePool();
        CustomerTableMap::clearInstancePool();
    }

    /**
     * @param array{?string, int}|null $after the invoice date and id of the last order read
     *
     * @return iterable<Order>
     */
    private function invoicedBatch(?\DateTimeInterface $from, ?\DateTimeInterface $to, ?array $after): iterable
    {
        $query = $this->period(OrderQuery::create(), $from, $to)
            ->where('Order.InvoiceRef IS NOT NULL')
            ->where('Order.InvoiceRef <> \'\'');

        if (null !== $after) {
            $query
                ->condition('later', 'Order.InvoiceDate > ?', $after[0])
                ->condition('same_date', 'Order.InvoiceDate = ?', $after[0])
                ->condition('next_id', 'Order.Id > ?', $after[1])
                ->combine(['same_date', 'next_id'], Criteria::LOGICAL_AND, 'same_date_next_id')
                ->where(['later', 'same_date_next_id'], Criteria::LOGICAL_OR);
        }

        return $query
            ->leftJoinWithCustomer()
            ->leftJoinWithOrderAddressRelatedByInvoiceOrderAddressId()
            ->leftJoinWithCurrency()
            ->orderByInvoiceDate()
            ->orderById()
            ->limit(self::BATCH_SIZE)
            ->find();
    }

    private function period(OrderQuery $query, ?\DateTimeInterface $from, ?\DateTimeInterface $to): OrderQuery
    {
        $query->filterByInvoiceDate(null, Criteria::ISNOTNULL);

        $range = array_filter(['min' => $from, 'max' => $to]);

        return [] === $range ? $query : $query->filterByInvoiceDate($range);
    }

    public static function amount(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', \STR_PAD_LEFT);
    }
}
