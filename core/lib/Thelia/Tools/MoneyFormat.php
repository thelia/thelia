<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
            // FIXME: should be a parameter related to i18n configuration
            $number = $number . ' ' . $symbol;
        }
        return $number;
    }
}
