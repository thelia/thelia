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

use Thelia\Model\ConfigQuery;

/**
 * The accounts the sales entries are written to, as the merchant sets them in the shop
 * settings: the customer account, the shipping account, and for each tax rate the product
 * account and the account of the tax collected. Nothing is guessed: an export refuses to
 * run on a chart that misses one of them.
 *
 * The rate accounts are one setting, "20:706200:445720,5.5:706055:445705,0:706000": the
 * rate in percent, the product account, and the tax account, which a rate of 0 does not
 * need.
 */
final readonly class AccountingChart
{
    public const JOURNAL_CODE_KEY = 'accounting_journal_code';
    public const JOURNAL_LABEL_KEY = 'accounting_journal_label';
    public const CUSTOMER_ACCOUNT_KEY = 'accounting_customer_account';
    public const SHIPPING_ACCOUNT_KEY = 'accounting_shipping_account';
    public const RATE_ACCOUNTS_KEY = 'accounting_rate_accounts';

    public const DEFAULT_JOURNAL_CODE = 'VE';
    public const DEFAULT_JOURNAL_LABEL = 'Ventes';

    private const ACCOUNT_PATTERN = '/^[0-9A-Za-z]{1,20}$/';

    /**
     * @param array<string, array{product: string, tax: ?string}> $rates by rate key
     */
    private function __construct(
        public string $journalCode,
        public string $journalLabel,
        public string $customerAccount,
        public string $shippingAccount,
        private array $rates,
    ) {
    }

    /**
     * @throws InvalidAccountingChartException
     */
    public static function fromSettings(): self
    {
        return self::fromValues(
            (string) ConfigQuery::read(self::JOURNAL_CODE_KEY, ''),
            (string) ConfigQuery::read(self::JOURNAL_LABEL_KEY, ''),
            (string) ConfigQuery::read(self::CUSTOMER_ACCOUNT_KEY, ''),
            (string) ConfigQuery::read(self::SHIPPING_ACCOUNT_KEY, ''),
            (string) ConfigQuery::read(self::RATE_ACCOUNTS_KEY, ''),
        );
    }

    /**
     * @throws InvalidAccountingChartException
     */
    public static function fromValues(string $journalCode, string $journalLabel, string $customerAccount, string $shippingAccount, string $rateAccounts): self
    {
        $customerAccount = trim($customerAccount);
        $shippingAccount = trim($shippingAccount);

        foreach (['customer' => $customerAccount, 'shipping' => $shippingAccount] as $role => $account) {
            if ('' !== $account && 1 !== preg_match(self::ACCOUNT_PATTERN, $account)) {
                throw new InvalidAccountingChartException(\sprintf('The %s account "%s" is not an account number: letters and digits only, 20 at most.', $role, $account));
            }
        }

        $rates = [];

        foreach (explode(',', $rateAccounts) as $rawRate) {
            $rawRate = trim($rawRate);

            if ('' === $rawRate) {
                continue;
            }

            $parts = array_map('trim', explode(':', $rawRate));

            if (!is_numeric($parts[0]) || (float) $parts[0] < 0 || !isset($parts[1]) || 1 !== preg_match(self::ACCOUNT_PATTERN, $parts[1])) {
                throw new InvalidAccountingChartException(\sprintf('"%s" is not the accounts of a tax rate: write the rate in percent, the product account, then the tax account, as in "20:706200:445720".', $rawRate));
            }

            $key = self::rateKey((float) $parts[0]);
            $taxAccount = isset($parts[2]) && '' !== $parts[2] ? $parts[2] : null;

            if (null !== $taxAccount && 1 !== preg_match(self::ACCOUNT_PATTERN, $taxAccount)) {
                throw new InvalidAccountingChartException(\sprintf('The tax account "%s" of the %s%% rate is not an account number.', $taxAccount, $key));
            }

            if (null === $taxAccount && (float) $key > 0) {
                throw new InvalidAccountingChartException(\sprintf('The %s%% rate needs the account of the tax it collects.', $key));
            }

            if (isset($rates[$key])) {
                throw new InvalidAccountingChartException(\sprintf('The %s%% rate is given twice.', $key));
            }

            $rates[$key] = ['product' => $parts[1], 'tax' => $taxAccount];
        }

        return new self(
            '' !== trim($journalCode) ? trim($journalCode) : self::DEFAULT_JOURNAL_CODE,
            '' !== trim($journalLabel) ? trim($journalLabel) : self::DEFAULT_JOURNAL_LABEL,
            $customerAccount,
            $shippingAccount,
            $rates,
        );
    }

    /**
     * The rate an amount is filed under: its percentage to the hundredth.
     */
    public static function rateKey(float $percent): string
    {
        return number_format(round($percent, 2), 2, '.', '');
    }

    public const MISSING_CUSTOMER_ACCOUNT = 'customer_account';
    public const MISSING_SHIPPING_ACCOUNT = 'shipping_account';
    public const MISSING_RATE_ACCOUNTS = 'rate_accounts';

    /**
     * What an export needs and the chart does not give, empty when it can run.
     *
     * @return list<self::MISSING_*>
     */
    public function missing(): array
    {
        $missing = [];

        if ('' === $this->customerAccount) {
            $missing[] = self::MISSING_CUSTOMER_ACCOUNT;
        }

        if ('' === $this->shippingAccount) {
            $missing[] = self::MISSING_SHIPPING_ACCOUNT;
        }

        if ([] === $this->rates) {
            $missing[] = self::MISSING_RATE_ACCOUNTS;
        }

        return $missing;
    }

    /**
     * @return array{product: string, tax: ?string}|null
     */
    public function accountsOf(string $rateKey): ?array
    {
        return $this->rates[$rateKey] ?? null;
    }

    public function rateAccountsSetting(): string
    {
        $parts = [];

        foreach ($this->rates as $key => $accounts) {
            $parts[] = rtrim(rtrim($key, '0'), '.').':'.$accounts['product'].(null !== $accounts['tax'] ? ':'.$accounts['tax'] : '');
        }

        return implode(',', $parts);
    }
}
