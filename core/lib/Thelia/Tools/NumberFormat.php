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
     * @param float $number   the number
     * @param string $decimals number of decimal figures
     * @return string
     */
    public function formatStandardNumber($number, $decimals = null)
    {
        $lang = $this->request->getSession()->getLang();

        if ($decimals === null) {
            $decimals = $lang->getDecimals();
        }

        return number_format($number, $decimals, '.', '');
    }

    public function format($number, $decimals = null, $decPoint = null, $thousandsSep = null)
    {
        $lang = $this->request->getSession()->getLang();

        if ($decimals === null) {
            $decimals = $lang->getDecimals();
        }
        if ($decPoint === null) {
            $decPoint = $lang->getDecimalSeparator();
        }
        if ($thousandsSep === null) {
            $thousandsSep = $lang->getThousandsSeparator();
        }
        return number_format($number, $decimals, $decPoint, $thousandsSep);
    }
}
