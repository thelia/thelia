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

namespace Thelia\Exception;

class TaxEngineException extends \RuntimeException
{
    const UNKNOWN_EXCEPTION = 0;

    const BAD_RECORDED_TYPE = 101;
    const BAD_RECORDED_REQUIREMENTS = 102;

    const TAX_TYPE_BAD_ABSTRACT_METHOD = 201;
    const TAX_TYPE_REQUIREMENT_NOT_FOUND = 202;
    const TAX_TYPE_BAD_REQUIREMENT_VALUE = 203;

    const UNDEFINED_PRODUCT = 501;
    const UNDEFINED_COUNTRY = 502;
    const UNDEFINED_TAX_RULES_COLLECTION = 503;
    const UNDEFINED_REQUIREMENTS = 504;
    const UNDEFINED_REQUIREMENT_VALUE = 505;
    const UNDEFINED_TAX_RULE = 506;
    const NO_TAX_IN_TAX_RULES_COLLECTION = 507;

    const BAD_AMOUNT_FORMAT = 601;

    const FEATURE_BAD_EXPECTED_VALUE = 701;

    public function __construct($message, $code = null, $previous = null)
    {
        if ($code === null) {
            $code = self::UNKNOWN_EXCEPTION;
        }
        parent::__construct($message, $code, $previous);
    }
}
