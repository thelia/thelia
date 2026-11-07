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

use Thelia\Core\Translation\Translator;
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
final class CreditPieceBuilder
{
    private ?string $locale = null;

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

        $entries = [new AccountingEntry(AccountingEntry::ROLE_CUSTOMER, $chart->customerAccount, null, $totalCents, 0)];

        foreach (PieceAmounts::byRate(array_filter($productCents)) as $rate => $cents) {
            $account = $this->accountsOf($chart, $rate, $ref)['product'];
            $entries[] = new AccountingEntry(AccountingEntry::ROLE_PRODUCT, $note->isCommercial() ? $chart->commercialCreditAccount ?? $account : $account, $rate, 0, $cents);
        }

        foreach (PieceAmounts::byRate(array_filter($shippingCents)) as $rate => $cents) {
            $entries[] = new AccountingEntry(AccountingEntry::ROLE_SHIPPING, $chart->shippingAccount, $rate, 0, $cents);
        }

        foreach (PieceAmounts::byRate(array_filter($taxCents)) as $rate => $cents) {
            $taxAccount = $this->accountsOf($chart, $rate, $ref)['tax'] ?? throw new OrderNotExportableException($this->trans('Credit note %ref: it gives back tax at %rate, a rate the chart of accounts gives no tax account.', ['%ref' => $ref, '%rate' => $rate.'%']));
            $entries[] = new AccountingEntry(AccountingEntry::ROLE_TAX, $taxAccount, $rate, 0, $cents);
        }

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
        $product = self::largest($products);
        $postage = self::largest($shipping);

        if (null !== $product && (null === $postage || abs($products[$product]) >= abs($shipping[$postage]))) {
            $products[$product] += $difference;
        } elseif (null !== $postage) {
            $shipping[$postage] += $difference;
        }
    }

    /**
     * @param array<string, int> $amounts
     */
    private static function largest(array $amounts): ?string
    {
        $largest = null;

        foreach ($amounts as $rate => $cents) {
            if (null === $largest || abs($cents) > abs($amounts[$largest])) {
                $largest = (string) $rate;
            }
        }

        return $largest;
    }

    /**
     * @return array{product: string, tax: ?string}
     */
    private function accountsOf(AccountingChart $chart, string $rate, string $ref): array
    {
        return $chart->accountsOf($rate) ?? throw new OrderNotExportableException($this->trans('Credit note %ref: it gives back an amount taxed at %rate, a rate the chart of accounts has no account for.', ['%ref' => $ref, '%rate' => $rate.'%']));
    }

    /**
     * @param array<string, string> $parameters
     */
    private function trans(string $message, array $parameters): string
    {
        return Translator::getInstance()->trans($message, $parameters, null, $this->locale);
    }
}
