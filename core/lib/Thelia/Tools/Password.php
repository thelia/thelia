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

/**
 * Class Password
 * @package Thelia\Tools
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Password
{
    private static function randgen($letter, $length)
    {
        $string = "";
        do {
            $string .= substr(str_shuffle($letter), 0, 1);
        } while (strlen($string) < $length);

        return $string;
    }

    /**
     * generate a Random password with defined length
     *
     * @param  int   $length
     * @return mixed
     */
    public static function generateRandom($length = 8)
    {
        $letter = "abcdefghijklmnopqrstuvwxyz";
        $letter .= "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $letter .= "0123456789";

        return self::randgen($letter, $length);
    }

    public static function generateHexaRandom($length = 8)
    {
        $letter = "ABCDEF";
        $letter .= "0123456789";

        return self::randgen($letter, $length);
    }
}
