<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
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


/**
 * Class Password
 * @package Thelia\Tools
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Password 
{

    private static function randgen($letter, $length) {

        return substr(str_shuffle($letter), 0, $length);
    }

    /**
     * generate a Random password with defined length
     *
     * @param int $length
     * @return mixed
     */
    public static function generateRandom($length = 8){

        $letter = "abcdefghijklmnopqrstuvwxyz";
        $letter .= "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $letter .= "0123456789";

        return self::randgen($letter, $length);
    }
} 