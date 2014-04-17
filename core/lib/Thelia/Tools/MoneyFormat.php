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
     */
    public function formatStandardMoney($number, $decimals = null)
    {
        return parent::formatStandardNumber($number, $decimals);
    }

    public function format($number, $decimals = null, $decPoint = null, $thousandsSep = null, $symbol = null)
    {
        $number = parent::format($number, $decimals, $decPoint, $thousandsSep);

        if ($symbol !== null) {
            $number = $number . ' ' . $symbol;
        }

        return $number;
    }
}
