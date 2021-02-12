<?php

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
    public static function getInstance(Request $request)
    {
        return new MoneyFormat($request);
    }

    /**
     * Get a standard number, with '.' as decimal point no thousands separator, and no currency symbol
     * so that this number can be used to perform calculations.
     *
     * @param float  $number   the number
     * @param string $decimals number of decimal figures
     *
     * @return string
     */
    public function formatStandardMoney($number, $decimals = null)
    {
        return parent::formatStandardNumber($number, $decimals);
    }

    public function format(
        $number,
        $decimals = null,
        $decPoint = null,
        $thousandsSep = null,
        $symbol = null,
        $removeZeroDecimal = false
    ) {
        $number = $this->preFormat($number, $decimals, $decPoint, $thousandsSep, $removeZeroDecimal);

        if ($symbol !== null) {
            return $number.' '.$symbol;
        }

        return $number;
    }

    /**
     * @since 2.3
     *
     * @param float    $number
     * @param int      $decimals
     * @param string   $decPoint
     * @param string   $thousandsSep
     * @param int|null $currencyId
     * @param bool     $removeZeroDecimal
     *
     * @return string
     */
    public function formatByCurrency(
        $number,
        $decimals = null,
        $decPoint = null,
        $thousandsSep = null,
        $currencyId = null,
        $removeZeroDecimal = false
    ) {
        $number = $this->preFormat($number, $decimals, $decPoint, $thousandsSep, $removeZeroDecimal);

        $currency = $currencyId !== null ? CurrencyQuery::create()->findPk($currencyId) : $this->request->getSession()->getCurrency();

        if ($currency !== null && strpos($currency->getFormat(), '%n') !== false) {
            return str_replace(
                ['%n', '%s', '%c'],
                [$number, $currency->getSymbol(), $currency->getCode()],
                $currency->getFormat()
            );
        }

        return $number;
    }

    /**
     * @param $number
     * @param null $decimals
     * @param null $decPoint
     * @param null $thousandsSep
     * @param bool $removeZeroDecimal
     *
     * @return string
     */
    protected function preFormat(
        $number,
        $decimals = null,
        $decPoint = null,
        $thousandsSep = null,
        $removeZeroDecimal = false
    ) {
        $number = preg_replace('/\s+/', '', $number);

        if ($removeZeroDecimal === true) {
            if ($number == (int) $number) {
                $number = \intval($number);
            }
        }

        return parent::format($number, $decimals, $decPoint, $thousandsSep);
    }
}
