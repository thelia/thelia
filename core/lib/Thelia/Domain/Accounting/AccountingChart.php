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

    private const JOURNAL_LABEL_MAX_LENGTH = 100;

    /**
     * How far, in points, a rate the frozen amounts of an order make may be from the rate of
     * the chart it is filed under, when the tax itself is more than a cent away.
     */
    public const RATE_TOLERANCE = 0.1;

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
        $journalCode = trim($journalCode);
        $journalLabel = trim($journalLabel);

        if ('' !== $journalCode && 1 !== preg_match(self::ACCOUNT_PATTERN, $journalCode)) {
            throw new InvalidAccountingChartException(self::trans('The journal code "%code" is not a code: letters and digits only, 20 at most.', ['%code' => $journalCode]));
        }

        if (mb_strlen($journalLabel) > self::JOURNAL_LABEL_MAX_LENGTH || 1 === preg_match('/[\x00-\x1F]/', $journalLabel)) {
            throw new InvalidAccountingChartException(self::trans('The journal label holds %max characters at most, on one line.', ['%max' => (string) self::JOURNAL_LABEL_MAX_LENGTH]));
        }

        if ('' !== $customerAccount && 1 !== preg_match(self::ACCOUNT_PATTERN, $customerAccount)) {
            throw new InvalidAccountingChartException(self::trans('The customer account "%account" is not an account number: letters and digits only, 20 at most.', ['%account' => $customerAccount]));
        }

        if ('' !== $shippingAccount && 1 !== preg_match(self::ACCOUNT_PATTERN, $shippingAccount)) {
            throw new InvalidAccountingChartException(self::trans('The shipping account "%account" is not an account number: letters and digits only, 20 at most.', ['%account' => $shippingAccount]));
        }

        $rates = [];

        foreach (explode(',', $rateAccounts) as $rawRate) {
            $rawRate = trim($rawRate);

            if ('' === $rawRate) {
                continue;
            }

            $parts = array_map('trim', explode(':', $rawRate));

            if (!is_numeric($parts[0]) || (float) $parts[0] < 0 || !isset($parts[1]) || 1 !== preg_match(self::ACCOUNT_PATTERN, $parts[1])) {
                throw new InvalidAccountingChartException(self::trans('"%rate" is not the accounts of a tax rate: write the rate in percent, the product account, then the tax account, as in "20:706200:445720".', ['%rate' => $rawRate]));
            }

            $key = self::rateKey((float) $parts[0]);
            $taxAccount = isset($parts[2]) && '' !== $parts[2] ? $parts[2] : null;

            if (null !== $taxAccount && 1 !== preg_match(self::ACCOUNT_PATTERN, $taxAccount)) {
                throw new InvalidAccountingChartException(self::trans('The tax account "%account" of the %rate rate is not an account number.', ['%account' => $taxAccount, '%rate' => $key.'%']));
            }

            if (null === $taxAccount && (float) $key > 0) {
                throw new InvalidAccountingChartException(self::trans('The %rate rate needs the account of the tax it collects.', ['%rate' => $key.'%']));
            }

            if (isset($rates[$key])) {
                throw new InvalidAccountingChartException(self::trans('The %rate rate is given twice.', ['%rate' => $key.'%']));
            }

            $rates[$key] = ['product' => $parts[1], 'tax' => $taxAccount];
        }

        return new self(
            '' !== $journalCode ? $journalCode : self::DEFAULT_JOURNAL_CODE,
            '' !== $journalLabel ? $journalLabel : self::DEFAULT_JOURNAL_LABEL,
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

    /**
     * The rate of the chart an amount and its frozen tax are filed under: a rate whose tax on
     * that amount, rounded to the cent, is within a cent of the frozen tax (the tax of 0.99
     * at 20% was rounded to 0.20, which makes 20.20%), or else a rate within RATE_TOLERANCE
     * of the one the two amounts make. The closest wins; null when none fits.
     */
    public function rateFor(float $amount, float $tax): ?string
    {
        $computed = $amount > 0 ? $tax / $amount * 100 : 0.0;
        $best = null;
        $bestGap = \PHP_FLOAT_MAX;

        foreach (array_keys($this->rates) as $chartRate) {
            $chartRate = (string) $chartRate;
            $taxGap = abs($tax - round($amount * (float) $chartRate / 100, 2));
            $rateGap = abs((float) $chartRate - $computed);

            if ($taxGap > 0.0101 && $rateGap > self::RATE_TOLERANCE + 0.0001) {
                continue;
            }

            // Two rates that fit: the one closest to what the amounts make.
            if ($rateGap < $bestGap) {
                $best = $chartRate;
                $bestGap = $rateGap;
            }
        }

        return $best;
    }

    public function rateAccountsSetting(): string
    {
        $parts = [];

        foreach ($this->rates as $key => $accounts) {
            $parts[] = rtrim(rtrim($key, '0'), '.').':'.$accounts['product'].(null !== $accounts['tax'] ? ':'.$accounts['tax'] : '');
        }

        return implode(',', $parts);
    }

    /**
     * @param array<string, string> $parameters
     */
    private static function trans(string $message, array $parameters): string
    {
        try {
            return Translator::getInstance()->trans($message, $parameters);
        } catch (\RuntimeException) {
            // Read outside a booted shop (a unit test, a script): the English wording.
            return strtr($message, $parameters);
        }
    }
}
