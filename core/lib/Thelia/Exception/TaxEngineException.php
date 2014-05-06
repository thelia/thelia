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
