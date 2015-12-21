<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

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
        $symbol = null
    ) {
        $number = parent::format($number, $decimals, $decPoint, $thousandsSep);

        if ($symbol !== null) {
            return $number . ' ' . $symbol;
        }

        return $number;
    }

    /**
     * @since 2.3
     * @param float $number
     * @param int $decimals
     * @param string $decPoint
     * @param string $thousandsSep
     * @param int|null $currencyId
     * @return string
     */
    public function formatByCurrency(
        $number,
        $decimals = null,
        $decPoint = null,
        $thousandsSep = null,
        $currencyId = null
    ) {
        $number = parent::format($number, $decimals, $decPoint, $thousandsSep);

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
}
