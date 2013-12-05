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

class NumberFormat
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public static function getInstance(Request $request)
    {
        return new NumberFormat($request);
    }

    /**
     * Get a standard number, with '.' as decimal point and no thousands separator
     * so that this number can be used to perform calculations.
     *
     * @param float $number the number
     * @param string $decimals number of decimal figures
     */
    public function formatStandardNumber($number, $decimals = null) {

        $lang = $this->request->getSession()->getLang();

        if ($decimals == null) $decimals = $lang->getDecimals();

        return number_format($number, $decimals, '.', '');
    }

    public function format($number, $decimals = null, $decPoint = null, $thousandsSep = null)
    {
        $lang = $this->request->getSession()->getLang();

        if ($decimals == null) $decimals = $lang->getDecimals();
        if ($decPoint == null) $decPoint = $lang->getDecimalSeparator();
        if ($thousandsSep == null) $thousandsSep = $lang->getThousandsSeparator();
        return number_format($number, $decimals, $decPoint, $thousandsSep);
    }
}
