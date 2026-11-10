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

/**
 * What the builders of the pieces of the journal share: the entries of a piece, written as
 * those of an invoice, and the language of what they say.
 */
abstract class AbstractPieceBuilder
{
    protected ?string $locale = null;

    /**
     * The customer debited of the total, the sales, the shipping and the tax credited, each by
     * rate with the highest rate first.
     *
     * @param array<string, int> $products       by rate, in cents
     * @param array<string, int> $shipping       by rate, in cents
     * @param array<string, int> $taxes          by rate, in cents
     * @param string|null        $productAccount the account of the sales instead of the one of their rate
     *
     * @return list<AccountingEntry>
     *
     * @throws OrderNotExportableException when the chart of accounts has no account for a rate
     */
    protected function invoiceEntries(AccountingChart $chart, string $ref, int $totalCents, array $products, array $shipping, array $taxes, ?string $productAccount = null): array
    {
        $entries = [new AccountingEntry(AccountingEntry::ROLE_CUSTOMER, $chart->customerAccount, null, $totalCents, 0)];

        foreach (PieceAmounts::byRate($products) as $rate => $cents) {
            $account = $this->accountsOf($chart, $rate, $ref)['product'];
            $entries[] = new AccountingEntry(AccountingEntry::ROLE_PRODUCT, $productAccount ?? $account, $rate, 0, $cents);
        }

        // The shipping has an account of its own: only its tax needs the accounts of its rate.
        foreach (PieceAmounts::byRate($shipping) as $rate => $cents) {
            $entries[] = new AccountingEntry(AccountingEntry::ROLE_SHIPPING, $chart->shippingAccount, $rate, 0, $cents);
        }

        foreach (PieceAmounts::byRate($taxes) as $rate => $cents) {
            $taxAccount = $this->accountsOf($chart, $rate, $ref)['tax'] ?? throw new OrderNotExportableException($this->noTaxAccountMessage($ref, $rate));
            $entries[] = new AccountingEntry(AccountingEntry::ROLE_TAX, $taxAccount, $rate, 0, $cents);
        }

        return $entries;
    }

    abstract protected function noAccountsMessage(string $ref, string $rate): string;

    abstract protected function noTaxAccountMessage(string $ref, string $rate): string;

    /**
     * @return array{product: string, tax: ?string}
     */
    private function accountsOf(AccountingChart $chart, string $rate, string $ref): array
    {
        return $chart->accountsOf($rate) ?? throw new OrderNotExportableException($this->noAccountsMessage($ref, $rate));
    }

    /**
     * @param array<string, string> $parameters
     */
    protected function trans(string $message, array $parameters): string
    {
        return Translator::getInstance()->trans($message, $parameters, null, $this->locale);
    }
}
