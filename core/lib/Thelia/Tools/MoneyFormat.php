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

namespace Thelia\Tools;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\CurrencyQuery;

class MoneyFormat extends NumberFormat
{
    public static function getInstance(Request $request): self
    {
        return new self($request);
    }

    /**
     * Get a standard number, with '.' as decimal point no thousands separator, and no currency symbol
     * so that this number can be used to perform calculations.
     *
     * @param float  $number   the number
     * @param string $decimals number of decimal figures
     */
    public function formatStandardMoney(float $number, ?string $decimals = null): string
    {
        return parent::formatStandardNumber($number, $decimals);
    }

    public function format(
        $number,
        $decimals = null,
        $decPoint = null,
        $thousandsSep = null,
        $symbol = null,
        $removeZeroDecimal = false,
    ): string {
        $number = $this->preFormat($number, $decimals, $decPoint, $thousandsSep, $removeZeroDecimal);

        if (null !== $symbol) {
            return $number.' '.$symbol;
        }

        return $number;
    }

    public function formatByCurrency(
        float $number,
        ?int $decimals = null,
        ?string $decPoint = null,
        ?string $thousandsSep = null,
        ?int $currencyId = null,
        bool $removeZeroDecimal = false,
    ): string {
        $number = $this->preFormat($number, $decimals, $decPoint, $thousandsSep, $removeZeroDecimal);

        $currency = null !== $currencyId ? CurrencyQuery::create()->findPk($currencyId) : $this->request->getSession()->getCurrency();

        if (null !== $currency && str_contains((string) $currency->getFormat(), '%n')) {
            return str_replace(
                ['%n', '%s', '%c'],
                [$number, $currency->getSymbol(), $currency->getCode()],
                $currency->getFormat(),
            );
        }

        return $number;
    }

    protected function preFormat(
        $number,
        $decimals = null,
        $decPoint = null,
        $thousandsSep = null,
        bool $removeZeroDecimal = false,
    ): string {
        $number = preg_replace('/\s+/', '', (string) $number);

        if ($removeZeroDecimal) {
            if (null === $decimals) {
                $decimals = $this->request->getSession()->getLang()->getDecimals();
            }

            $number = round($number, $decimals);

            $asFloat = $number;
            $asInt = (int) $asFloat;

            if (($asFloat - $asInt) === 0.0) {
                $decimals = 0;
            }
        }

        return parent::format($number, $decimals, $decPoint, $thousandsSep);
    }
}
