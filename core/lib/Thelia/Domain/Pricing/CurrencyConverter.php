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

namespace Thelia\Domain\Pricing;

use Thelia\Model\Currency;

/**
 * Converts an amount typed in the default currency into another currency, at the
 * rates of the shop: `amount * rate / default rate`, the formula
 * ProductSaleElements::getPricesByCurrency() applies to the catalog.
 *
 * Every reader of a price that falls back on the default currency converts here, so
 * that two paths pricing the same sale element agree to the last digit.
 */
final readonly class CurrencyConverter
{
    /**
     * @return float|null null when the default currency has no usable rate: nothing
     *                    can be converted, and the amount has no price in that currency
     */
    public function fromDefaultCurrency(float $amount, Currency $currency): ?float
    {
        $factor = $this->factor($currency);

        return null === $factor ? null : $amount * $factor;
    }

    /**
     * What an amount typed in the default currency is multiplied by to be expressed in
     * the currency (`rate / default rate`): for the readers that convert in SQL, over a
     * whole page, where fromDefaultCurrency() cannot be called per amount.
     *
     * @return float|null null when the default currency has no usable rate
     */
    public function factor(Currency $currency): ?float
    {
        $defaultRate = (float) Currency::getDefaultCurrency()->getRate();

        if ($defaultRate <= 0.0) {
            return null;
        }

        return (float) $currency->getRate() / $defaultRate;
    }

    /**
     * The factor as a SQL literal, `NULL` when there is none: an amount multiplied by it
     * then has no value, the same "no price" fromDefaultCurrency() answers with null.
     */
    public function factorAsSql(Currency $currency): string
    {
        $factor = $this->factor($currency);

        return null === $factor ? 'NULL' : number_format($factor, 12, '.', '');
    }
}
