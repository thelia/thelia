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
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Order;

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
 * Each rate starts from its frozen amounts, rounded line by line as the invoice rounds them,
 * and is brought to the totals of the invoice in whole cents: what a discount took off is
 * spread over the rates, a cent of rounding left over goes to the largest line.
 *
 * An order in another currency than the shop's is booked in the shop currency at the rate
 * frozen on the order, each line carrying its own amount.
 *
 * The journal hands the frozen amounts of a whole batch of orders, read at once; an order
 * built alone reads its own the same way, as a batch of one.
 */
final class SalesPieceBuilder
{
    private ?string $locale = null;

    /**
     * @throws OrderNotExportableException
     */
    public function build(Order $order, AccountingChart $chart, Currency $shopCurrency, ?string $locale = null, ?FrozenOrderAmounts $frozen = null): AccountingPiece
    {
        $this->locale = $locale;
        $orderRef = (string) $order->getRef();
        $frozen ??= FrozenOrderAmounts::ofOrders([(int) $order->getId()])[(int) $order->getId()];
        [$productAmounts, $productTaxes, $productLines] = $this->productAmountsByRate($order, $frozen, $chart);

        $tax = 0.0;
        $totalCents = PieceAmounts::cents($order->getTotalAmount($tax));
        $taxCents = PieceAmounts::cents((float) $tax);

        if ($totalCents <= 0) {
            throw new OrderNotExportableException($this->trans('Order %ref: its invoice amounts to nothing.', ['%ref' => $orderRef]));
        }

        $postage = (float) $order->getPostage();
        $postageTax = (float) $order->getPostageTax();
        $postageCents = PieceAmounts::cents($postage - $postageTax);
        $postageTaxCents = PieceAmounts::cents($postageTax);
        [$postageWeights, $postageTaxWeights] = $this->postageWeightsByRate($frozen, $postage - $postageTax, $postageTax, $chart);

        $productCents = $totalCents - $taxCents - $postageCents;
        $productTaxCents = $taxCents - $postageTaxCents;

        if ($productCents < 0 || $productTaxCents < 0) {
            throw new OrderNotExportableException($this->trans('Order %ref: its discount is larger than its products, the invoice cannot be split by rate.', ['%ref' => $orderRef]));
        }

        $discounted = (float) $order->getDiscount() > 0.0;
        $products = self::fromFrozen($productCents, $productAmounts, $discounted, $productAmounts);
        $shipping = self::fromFrozen($postageCents, $postageWeights, false, $postageWeights);
        $taxes = self::fromFrozen($productTaxCents, $productTaxes, $discounted, [] !== array_filter($productTaxes) ? $productTaxes : $productAmounts);

        foreach (self::fromFrozen($postageTaxCents, $postageTaxWeights, false, $postageTaxWeights) as $rate => $cents) {
            $taxes[$rate] = ($taxes[$rate] ?? 0) + $cents;
        }

        $notes = $discounted ? $this->taxesOffTheirRates($orderRef, $products, $shipping, $taxes, $productLines, \count($postageWeights)) : [];

        $entries = [new AccountingEntry(AccountingEntry::ROLE_CUSTOMER, $chart->customerAccount, null, $totalCents, 0)];

        foreach (PieceAmounts::byRate($products) as $rate => $cents) {
            $entries[] = new AccountingEntry(AccountingEntry::ROLE_PRODUCT, $this->accountsOf($chart, $rate, $orderRef)['product'], $rate, 0, $cents);
        }

        // The shipping has an account of its own: only its tax needs the accounts of its rate.
        foreach (PieceAmounts::byRate($shipping) as $rate => $cents) {
            $entries[] = new AccountingEntry(AccountingEntry::ROLE_SHIPPING, $chart->shippingAccount, $rate, 0, $cents);
        }

        foreach (PieceAmounts::byRate($taxes) as $rate => $cents) {
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
            $entries = PieceAmounts::inShopCurrency($entries, $rate);
        }

        $invoiceAddress = $order->getOrderAddressRelatedByInvoiceOrderAddressId();
        $customer = $order->getCustomer();
        $customerName = trim((string) $invoiceAddress?->getCompany()) ?: trim($invoiceAddress?->getFirstname().' '.$invoiceAddress?->getLastname());

        return new AccountingPiece(
            AccountingPiece::INVOICE,
            (int) $order->getId(),
            (string) $order->getInvoiceRef(),
            $order->getInvoiceDate() ?? throw new OrderNotExportableException($this->trans('Order %ref has no invoice date.', ['%ref' => $orderRef])),
            (string) $customer?->getRef(),
            $customerName,
            $foreignCurrencyCode,
            $entries,
            $notes,
        );
    }

    /**
     * What the products weigh by rate, excluding tax and in tax, at their frozen prices.
     * Only the proportions are used: the amounts booked are the totals of the invoice,
     * which Order::getTotalAmount() computes under the rounding rule of the order.
     *
     * @return array{array<string, float>, array<string, float>, array<string, int>}
     */
    private function productAmountsByRate(Order $order, FrozenOrderAmounts $frozen, AccountingChart $chart): array
    {
        $amounts = [];
        $taxes = [];
        $counts = [];

        foreach ($frozen->lines as ['quantity' => $quantity, 'unitPrice' => $unitPrice, 'unitTaxes' => $unitTaxAmounts]) {
            $unitTax = array_sum($unitTaxAmounts);

            if ($unitPrice <= 0.0 && $unitTax <= 0.0) {
                continue;
            }

            $rate = PieceAmounts::rateOf($chart, $unitPrice, $unitTax);
            [$lineAmount, $lineTax] = self::lineAsInvoiced($order, $quantity, $unitPrice, $unitTaxAmounts);
            $amounts[$rate] = ($amounts[$rate] ?? 0.0) + $lineAmount;
            $taxes[$rate] = ($taxes[$rate] ?? 0.0) + $lineTax;
            $counts[$rate] = ($counts[$rate] ?? 0) + 1;
        }

        return [$amounts, $taxes, $counts];
    }

    /**
     * The rates whose tax is not the rate of their base, a cent per line aside. Thelia takes
     * the tax out of a discount at the average of the rates of the lines, not weighted by
     * their amounts: on an order mixing rates, the invoice then collects more tax at one rate
     * and less at another than its bases say. The journal books the invoice as it was issued
     * and names the order for the tax return.
     *
     * @param array<string, int> $products
     * @param array<string, int> $shipping
     * @param array<string, int> $taxes
     * @param array<string, int> $productLines
     *
     * @return list<string>
     */
    private function taxesOffTheirRates(string $orderRef, array $products, array $shipping, array $taxes, array $productLines, int $postageShares): array
    {
        $notes = [];

        foreach (PieceAmounts::byRate($taxes + array_fill_keys(array_keys($products + $shipping), 0)) as $rate => $taxCents) {
            $baseCents = ($products[$rate] ?? 0) + ($shipping[$rate] ?? 0);
            $expectedCents = (int) round($baseCents * (float) $rate / 100);
            $tolerance = max(1, ($productLines[$rate] ?? 0) + (isset($shipping[$rate]) ? max(1, $postageShares) : 0));

            if (abs($taxCents - $expectedCents) > $tolerance) {
                $notes[] = $this->trans('Order %ref: at %rate, the invoice collected %tax of tax on a base of %base, where the rate gives %expected. Its discount was untaxed at the average rate of its lines: check it before the tax return.', [
                    '%ref' => $orderRef,
                    '%rate' => $rate.'%',
                    '%tax' => SalesJournal::amount($taxCents),
                    '%base' => SalesJournal::amount($baseCents),
                    '%expected' => SalesJournal::amount($expectedCents),
                ]);
            }
        }

        return $notes;
    }

    /**
     * A line excluding tax and its tax, rounded as Order::getTotalAmount() rounds them for the
     * invoice: each unit amount to the cent before the quantity under the sum of roundings,
     * the line total under the rounding of sums, nothing for an order older than both.
     *
     * @param list<float> $unitTaxes
     *
     * @return array{float, float}
     */
    private static function lineAsInvoiced(Order $order, float $quantity, float $unitPrice, array $unitTaxes): array
    {
        $orderId = (int) $order->getId();

        if (ConfigQuery::isOrderWithLegacyRounding($orderId)) {
            return [$quantity * $unitPrice, $quantity * array_sum($unitTaxes)];
        }

        if (ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS === ConfigQuery::getOrderRoundingMode($orderId)) {
            $amount = round($quantity * $unitPrice, 2);

            return [$amount, round($quantity * ($unitPrice + array_sum($unitTaxes)), 2) - $amount];
        }

        return [
            $quantity * round($unitPrice, 2),
            $quantity * array_sum(array_map(static fn (float $tax): float => round($tax, 2), $unitTaxes)),
        ];
    }

    /**
     * @return array{array<string, float>, array<string, float>}
     */
    private function postageWeightsByRate(FrozenOrderAmounts $frozen, float $untaxedPostage, float $postageTax, AccountingChart $chart): array
    {
        $amounts = [];
        $taxes = [];

        foreach ($frozen->postageShares as ['untaxed' => $untaxed, 'tax' => $tax]) {
            $rate = PieceAmounts::rateOf($chart, $untaxed, $tax);
            $amounts[$rate] = ($amounts[$rate] ?? 0.0) + $untaxed;
            $taxes[$rate] = ($taxes[$rate] ?? 0.0) + $tax;
        }

        if ([] === $amounts) {
            $rate = PieceAmounts::rateOf($chart, $untaxedPostage, $postageTax);
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
     * The amounts of each rate as the order froze them, rounded to the cent, brought to the
     * total of the invoice. What the discount took off is spread over the rates in proportion
     * of the weights; without discount, the difference is the rounding of the invoice and goes
     * to the largest line.
     *
     * @param array<string, float> $frozen
     * @param array<string, float> $weights
     *
     * @return array<string, int>
     */
    private static function fromFrozen(int $total, array $frozen, bool $discounted, array $weights): array
    {
        if ([] === $frozen) {
            return PieceAmounts::allocate($total, $weights);
        }

        $shares = array_map(PieceAmounts::cents(...), $frozen);
        $difference = $total - array_sum($shares);

        if (0 === $difference) {
            return array_filter($shares, static fn (int $share): bool => 0 !== $share);
        }

        if ($discounted) {
            $sign = $difference < 0 ? -1 : 1;

            foreach (PieceAmounts::allocate(abs($difference), $weights) as $rate => $cents) {
                $shares[$rate] = ($shares[$rate] ?? 0) + $sign * $cents;
            }
        } else {
            $largest = array_key_first($shares);

            foreach ($shares as $rate => $share) {
                if (abs($share) > abs($shares[$largest])) {
                    $largest = $rate;
                }
            }

            $shares[$largest] += $difference;
        }

        return array_filter($shares, static fn (int $share): bool => 0 !== $share);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function trans(string $message, array $parameters): string
    {
        return Translator::getInstance()->trans($message, $parameters, null, $this->locale);
    }
}
