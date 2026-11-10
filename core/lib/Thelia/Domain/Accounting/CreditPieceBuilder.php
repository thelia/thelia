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

use Thelia\Model\Currency;

/**
 * The entries of a credit note, balanced to the cent: the reverse of an invoice. The customer
 * is credited of the total of the credit note, the sales, the shipping and the tax it gives
 * back are debited by rate.
 *
 * - Each line is filed under the rate its unit amounts make, read from the chart: for a
 *   product of the order, the module copied the amounts frozen on the order.
 * - The discount is spread over the products in proportion of their amount in tax, and
 *   untaxed at the rate of each: the module copies the discount of an order to both of its
 *   fields, so only the amount in tax holds.
 * - A commercial credit note (rebate, discount, refund of a difference) is debited to the
 *   account of commercial credit notes when the chart has one.
 * - The total of the credit note is the anchor: a cent of rounding goes to the largest line.
 */
final class CreditPieceBuilder extends AbstractPieceBuilder
{
    /**
     * @throws OrderNotExportableException
     */
    public function build(CreditNoteRecord $note, AccountingChart $chart, Currency $shopCurrency, ?string $locale = null): AccountingPiece
    {
        $this->locale = $locale;
        $ref = $note->invoiceRef;
        $totalCents = PieceAmounts::cents($note->totalWithTax);

        if ($totalCents <= 0) {
            throw new OrderNotExportableException($this->trans('Credit note %ref: it amounts to nothing.', ['%ref' => $ref]));
        }

        [$products, $productsWithTax, $shipping, $shippingWithTax] = $this->amountsByRate($note, $chart);
        [$products, $productsWithTax] = self::lessTheDiscount($note->discountWithTax, $products, $productsWithTax);

        $productCents = array_map(PieceAmounts::cents(...), $products);
        $shippingCents = array_map(PieceAmounts::cents(...), $shipping);
        $taxCents = [];

        if ([] === $products && [] === $shipping) {
            throw new OrderNotExportableException($this->trans('Credit note %ref: none of its lines has an amount.', ['%ref' => $ref]));
        }

        foreach (array_keys($productsWithTax + $shippingWithTax) as $rate) {
            $taxCents[$rate] = PieceAmounts::cents(($productsWithTax[$rate] ?? 0.0) + ($shippingWithTax[$rate] ?? 0.0) - ($products[$rate] ?? 0.0) - ($shipping[$rate] ?? 0.0));
        }

        $notes = [];
        $difference = $totalCents - array_sum($productCents) - array_sum($shippingCents) - array_sum($taxCents);

        if (abs($difference) > \count($note->lines)) {
            $notes[] = $this->trans('Credit note %ref: its total is %difference away from its lines; the difference is booked on its largest line.', ['%ref' => $ref, '%difference' => SalesJournal::amount($difference)]);
        }

        if (0 !== $difference) {
            self::onTheLargest($difference, $productCents, $shippingCents);
        }

        $entries = $this->invoiceEntries($chart, $ref, $totalCents, array_filter($productCents), array_filter($shippingCents), array_filter($taxCents), $note->isCommercial() ? $chart->commercialCreditAccount : null);

        $foreignCurrencyCode = null;

        if ($note->currencyId !== (int) $shopCurrency->getId()) {
            if ($note->currencyRate <= 0) {
                throw new OrderNotExportableException($this->trans('Credit note %ref: it was issued in another currency without exchange rate, it cannot be booked in the shop currency.', ['%ref' => $ref]));
            }

            $foreignCurrencyCode = $note->currencyCode;
            $entries = PieceAmounts::inShopCurrency($entries, $note->currencyRate);
        }

        return new AccountingPiece(
            AccountingPiece::CREDIT_NOTE,
            $note->id,
            $ref,
            $note->invoiceDate,
            $note->customerRef,
            $note->customerName,
            $foreignCurrencyCode,
            array_map(static fn (AccountingEntry $entry): AccountingEntry => new AccountingEntry($entry->role, $entry->account, $entry->rateKey, $entry->creditCents, $entry->debitCents, $entry->foreignCents), $entries),
            $notes,
        );
    }

    /**
     * The products and the shipping by rate, excluding tax and in tax.
     *
     * @return array{array<string, float>, array<string, float>, array<string, float>, array<string, float>}
     */
    private function amountsByRate(CreditNoteRecord $note, AccountingChart $chart): array
    {
        $amounts = [[], [], [], []];

        foreach ($note->lines as $line) {
            $amount = $line->unitPrice * $line->quantity;
            $amountWithTax = $line->unitPriceWithTax * $line->quantity;

            if ($line->quantity <= 0 || (0.0 === $amount && 0.0 === $amountWithTax)) {
                continue;
            }

            $rate = PieceAmounts::rateOf($chart, $line->unitPrice, $line->unitPriceWithTax - $line->unitPrice);
            $offset = $line->isShipping() ? 2 : 0;
            $amounts[$offset][$rate] = ($amounts[$offset][$rate] ?? 0.0) + $amount;
            $amounts[$offset + 1][$rate] = ($amounts[$offset + 1][$rate] ?? 0.0) + $amountWithTax;
        }

        return $amounts;
    }

    /**
     * @param array<string, float> $products
     * @param array<string, float> $productsWithTax
     *
     * @return array{array<string, float>, array<string, float>}
     */
    private static function lessTheDiscount(float $discountWithTax, array $products, array $productsWithTax): array
    {
        foreach (PieceAmounts::allocate(PieceAmounts::cents($discountWithTax), $productsWithTax) as $rate => $cents) {
            if (!isset($productsWithTax[$rate])) {
                continue;
            }

            $productsWithTax[$rate] -= $cents / 100;
            $products[$rate] -= $cents / 100 / (1 + (float) $rate / 100);
        }

        return [$products, $productsWithTax];
    }

    /**
     * @param array<string, int> $products
     * @param array<string, int> $shipping
     */
    private static function onTheLargest(int $difference, array &$products, array &$shipping): void
    {
        $product = PieceAmounts::largest($products);
        $postage = PieceAmounts::largest($shipping);

        if (null !== $product && (null === $postage || abs($products[$product]) >= abs($shipping[$postage]))) {
            $products[$product] += $difference;
        } elseif (null !== $postage) {
            $shipping[$postage] += $difference;
        }
    }

    protected function noAccountsMessage(string $ref, string $rate): string
    {
        return $this->trans('Credit note %ref: it gives back an amount taxed at %rate, a rate the chart of accounts has no account for.', ['%ref' => $ref, '%rate' => $rate.'%']);
    }

    protected function noTaxAccountMessage(string $ref, string $rate): string
    {
        return $this->trans('Credit note %ref: it gives back tax at %rate, a rate the chart of accounts gives no tax account.', ['%ref' => $ref, '%rate' => $rate.'%']);
    }
}
