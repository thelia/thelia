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
use Thelia\Model\Currency;
use Thelia\Model\Order;
use Thelia\Model\OrderPostageTaxQuery;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderProductTaxQuery;

/**
 * The entries of an invoiced order, balanced to the cent.
 *
 * The invoice is the anchor: the customer is debited of the total Order::getTotalAmount()
 * gives, and the tax collected is its tax, so the journal says what the invoice handed to
 * the customer said, under the rounding rule the order was priced with, the rule of an
 * order older than the rounding rules included. Those totals are then split by tax rate:
 * - the products, by the rate their frozen taxes make on their frozen price, the discount
 *   spread over the rates in proportion of their amount excluding tax;
 * - the shipping, by the rules order_postage_tax froze when the postage was split, or at
 *   the one rate its frozen tax makes otherwise.
 * Every split is made in whole cents, the cents left over going to the largest shares, so
 * that the lines add up to the totals exactly.
 *
 * An order in another currency than the shop's is booked in the shop currency at the rate
 * frozen on the order, each line carrying its own amount.
 */
final class SalesPieceBuilder
{
    private ?string $locale = null;

    /**
     * @throws OrderNotExportableException
     */
    public function build(Order $order, AccountingChart $chart, Currency $shopCurrency, ?string $locale = null): AccountingPiece
    {
        $this->locale = $locale;
        $orderRef = (string) $order->getRef();
        [$productAmounts, $productTaxes] = $this->productAmountsByRate($order, $chart);

        $tax = 0.0;
        $totalCents = self::cents($order->getTotalAmount($tax));
        $taxCents = self::cents((float) $tax);

        if ($totalCents <= 0) {
            throw new OrderNotExportableException($this->trans('Order %ref: its invoice amounts to nothing.', ['%ref' => $orderRef]));
        }

        $postage = (float) $order->getPostage();
        $postageTax = (float) $order->getPostageTax();
        $postageCents = self::cents($postage - $postageTax);
        $postageTaxCents = self::cents($postageTax);
        [$postageWeights, $postageTaxWeights] = $this->postageWeightsByRate($order, $postage - $postageTax, $postageTax, $chart);

        $productCents = $totalCents - $taxCents - $postageCents;
        $productTaxCents = $taxCents - $postageTaxCents;

        if ($productCents < 0 || $productTaxCents < 0) {
            throw new OrderNotExportableException($this->trans('Order %ref: its discount is larger than its products, the invoice cannot be split by rate.', ['%ref' => $orderRef]));
        }

        $products = self::allocate($productCents, $productAmounts);
        $shipping = self::allocate($postageCents, $postageWeights);
        $taxes = self::allocate($productTaxCents, [] !== array_filter($productTaxes) ? $productTaxes : $productAmounts);

        foreach (self::allocate($postageTaxCents, $postageTaxWeights) as $rate => $cents) {
            $taxes[$rate] = ($taxes[$rate] ?? 0) + $cents;
        }

        $entries = [new AccountingEntry(AccountingEntry::ROLE_CUSTOMER, $chart->customerAccount, null, $totalCents, 0)];

        foreach (self::byRate($products) as $rate => $cents) {
            $entries[] = new AccountingEntry(AccountingEntry::ROLE_PRODUCT, $this->accountsOf($chart, $rate, $orderRef)['product'], $rate, 0, $cents);
        }

        // The shipping has an account of its own: only its tax needs the accounts of its rate.
        foreach (self::byRate($shipping) as $rate => $cents) {
            $entries[] = new AccountingEntry(AccountingEntry::ROLE_SHIPPING, $chart->shippingAccount, $rate, 0, $cents);
        }

        foreach (self::byRate($taxes) as $rate => $cents) {
            $taxAccount = $this->accountsOf($chart, $rate, $orderRef)['tax'] ?? throw new OrderNotExportableException($this->trans('Order %ref: tax was collected at %rate, a rate the chart of accounts gives no tax account.', ['%ref' => $orderRef, '%rate' => $rate.'%']));
            $entries[] = new AccountingEntry(AccountingEntry::ROLE_TAX, $taxAccount, $rate, 0, $cents);
        }

        $foreignCurrencyCode = null;

        if ((int) $order->getCurrencyId() !== (int) $shopCurrency->getId()) {
            $rate = (float) $order->getCurrencyRate();

            if ($rate <= 0) {
                throw new OrderNotExportableException($this->trans('Order %ref: it was placed in another currency without exchange rate, it cannot be booked in the shop currency.', ['%ref' => $orderRef]));
            }

            $foreignCurrencyCode = (string) $order->getCurrency()?->getCode();
            $entries = self::inShopCurrency($entries, $rate);
        }

        $invoiceAddress = $order->getOrderAddressRelatedByInvoiceOrderAddressId();
        $customer = $order->getCustomer();
        $customerName = trim((string) $invoiceAddress?->getCompany()) ?: trim($invoiceAddress?->getFirstname().' '.$invoiceAddress?->getLastname());

        return new AccountingPiece(
            (int) $order->getId(),
            (string) $order->getInvoiceRef(),
            $order->getInvoiceDate() ?? throw new OrderNotExportableException($this->trans('Order %ref has no invoice date.', ['%ref' => $orderRef])),
            (string) $customer?->getRef(),
            $customerName,
            $foreignCurrencyCode,
            $entries,
        );
    }

    /**
     * What the products weigh by rate, excluding tax and in tax, at their frozen prices.
     * Only the proportions are used: the amounts booked are the totals of the invoice,
     * which Order::getTotalAmount() computes under the rounding rule of the order.
     *
     * @return array{array<string, float>, array<string, float>}
     */
    private function productAmountsByRate(Order $order, AccountingChart $chart): array
    {
        $amounts = [];
        $taxes = [];
        $lines = OrderProductQuery::create()->filterByOrderId($order->getId())->find();
        $unitTaxes = [];

        foreach (OrderProductTaxQuery::create()->filterByOrderProductId(array_map(static fn ($line): int => (int) $line->getId(), iterator_to_array($lines, false)), Criteria::IN)->find() as $tax) {
            $unitTaxes[(int) $tax->getOrderProductId()][] = $tax;
        }

        foreach ($lines as $line) {
            $promo = 1 === (int) $line->getWasInPromo();
            $unitPrice = (float) ($promo ? $line->getPromoPrice() : $line->getPrice());
            $unitTax = 0.0;

            foreach ($unitTaxes[(int) $line->getId()] ?? [] as $tax) {
                $unitTax += (float) ($promo ? $tax->getPromoAmount() : $tax->getAmount());
            }

            if ($unitPrice <= 0.0 && $unitTax <= 0.0) {
                continue;
            }

            $rate = $this->rateOf($chart, $unitPrice, $unitTax);
            $amounts[$rate] = ($amounts[$rate] ?? 0.0) + (float) $line->getQuantity() * $unitPrice;
            $taxes[$rate] = ($taxes[$rate] ?? 0.0) + (float) $line->getQuantity() * $unitTax;
        }

        return [$amounts, $taxes];
    }

    /**
     * The rate the chart files an amount and its tax under (AccountingChart::rateFor()); the
     * rate the two amounts make when the chart has none that fits, for the refusal to name it.
     */
    private function rateOf(AccountingChart $chart, float $amount, float $tax): string
    {
        return $chart->rateFor($amount, $tax) ?? AccountingChart::rateKey($amount > 0 ? $tax / $amount * 100 : 0.0);
    }

    /**
     * @return array{array<string, float>, array<string, float>}
     */
    private function postageWeightsByRate(Order $order, float $untaxedPostage, float $postageTax, AccountingChart $chart): array
    {
        $amounts = [];
        $taxes = [];

        foreach (OrderPostageTaxQuery::create()->filterByOrderId($order->getId())->find() as $share) {
            $untaxed = (float) $share->getUntaxedAmount();
            $rate = $this->rateOf($chart, $untaxed, (float) $share->getAmount());
            $amounts[$rate] = ($amounts[$rate] ?? 0.0) + $untaxed;
            $taxes[$rate] = ($taxes[$rate] ?? 0.0) + (float) $share->getAmount();
        }

        if ([] === $amounts) {
            $rate = $this->rateOf($chart, $untaxedPostage, $postageTax);
            $amounts[$rate] = $untaxedPostage;
            $taxes[$rate] = $postageTax;
        }

        return [$amounts, $taxes];
    }

    /**
     * @return array{product: string, tax: ?string}
     */
    private function accountsOf(AccountingChart $chart, string $rate, string $orderRef): array
    {
        return $chart->accountsOf($rate) ?? throw new OrderNotExportableException($this->trans('Order %ref: it was taxed at %rate, a rate the chart of accounts has no account for.', ['%ref' => $orderRef, '%rate' => $rate.'%']));
    }

    /**
     * Splits whole cents in proportion of the weights; the cents the rounding leaves go to
     * the largest remainders, so the shares add up to the total exactly. Nothing to weigh
     * puts everything on the first key.
     *
     * @param array<string, float> $weights
     *
     * @return array<string, int>
     */
    private static function allocate(int $cents, array $weights): array
    {
        if (0 === $cents) {
            return [];
        }

        $sum = array_sum(array_map('abs', $weights));

        if ([] === $weights || $sum <= 0.0) {
            return [array_key_first($weights) ?? AccountingChart::rateKey(0.0) => $cents];
        }

        $shares = [];
        $remainders = [];

        foreach ($weights as $key => $weight) {
            $exact = $cents * abs($weight) / $sum;
            $shares[$key] = (int) floor($exact);
            $remainders[$key] = $exact - $shares[$key];
        }

        arsort($remainders);

        foreach (array_keys($remainders) as $key) {
            if (array_sum($shares) >= $cents) {
                break;
            }

            ++$shares[$key];
        }

        return array_filter($shares, static fn (int $share): bool => 0 !== $share);
    }

    /**
     * @param array<string, int> $amounts
     *
     * @return array<string, int> the highest rate first
     */
    private static function byRate(array $amounts): array
    {
        uksort($amounts, static fn (string $left, string $right): int => (float) $right <=> (float) $left);

        return $amounts;
    }

    /**
     * Each line converted at the rate of the order, the cent the conversion leaves on the
     * largest credit so that the piece still balances.
     *
     * @param list<AccountingEntry> $entries
     *
     * @return list<AccountingEntry>
     */
    private static function inShopCurrency(array $entries, float $rate): array
    {
        $rate = $rate > 0 ? $rate : 1.0;
        $converted = [];

        foreach ($entries as $entry) {
            $converted[] = new AccountingEntry(
                $entry->role,
                $entry->account,
                $entry->rateKey,
                (int) round($entry->debitCents / $rate),
                (int) round($entry->creditCents / $rate),
                $entry->debitCents + $entry->creditCents,
            );
        }

        $difference = array_sum(array_map(static fn (AccountingEntry $entry): int => $entry->debitCents - $entry->creditCents, $converted));

        if (0 !== $difference) {
            // On the largest sale rather than on a tax: the tax stays the converted tax.
            $largest = null;

            foreach ([AccountingEntry::ROLE_PRODUCT, AccountingEntry::ROLE_SHIPPING, AccountingEntry::ROLE_TAX] as $role) {
                foreach ($converted as $index => $entry) {
                    if ($role === $entry->role && $entry->creditCents > 0 && (null === $largest || $entry->creditCents > $converted[$largest]->creditCents)) {
                        $largest = $index;
                    }
                }

                if (null !== $largest) {
                    break;
                }
            }

            if (null !== $largest) {
                $entry = $converted[$largest];
                $converted[$largest] = new AccountingEntry($entry->role, $entry->account, $entry->rateKey, 0, $entry->creditCents + $difference, $entry->foreignCents);
            }
        }

        return array_values(array_filter($converted, static fn (AccountingEntry $entry): bool => 0 !== $entry->debitCents || 0 !== $entry->creditCents));
    }

    private static function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function trans(string $message, array $parameters): string
    {
        return Translator::getInstance()->trans($message, $parameters, null, $this->locale);
    }
}
