<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Exception;

class TaxEngineException extends \RuntimeException
{
    public const UNKNOWN_EXCEPTION = 0;
    public const BAD_RECORDED_TYPE = 101;
    public const BAD_RECORDED_REQUIREMENTS = 102;
    public const TAX_TYPE_BAD_ABSTRACT_METHOD = 201;
    public const TAX_TYPE_REQUIREMENT_NOT_FOUND = 202;
    public const TAX_TYPE_BAD_REQUIREMENT_VALUE = 203;
    public const UNDEFINED_PRODUCT = 501;
    public const UNDEFINED_COUNTRY = 502;
    public const UNDEFINED_TAX_RULES_COLLECTION = 503;
    public const UNDEFINED_REQUIREMENTS = 504;
    public const UNDEFINED_REQUIREMENT_VALUE = 505;
    public const UNDEFINED_TAX_RULE = 506;
    public const NO_TAX_IN_TAX_RULES_COLLECTION = 507;
    public const BAD_AMOUNT_FORMAT = 601;
    public const FEATURE_BAD_EXPECTED_VALUE = 701;

    public function __construct($message, $code = null, $previous = null)
    {
        if (null === $code) {
            $code = self::UNKNOWN_EXCEPTION;
        }

        parent::__construct($message, $code, $previous);
    }
}
