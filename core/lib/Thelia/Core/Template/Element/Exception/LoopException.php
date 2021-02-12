<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template\Element\Exception;

class LoopException extends \RuntimeException
{
    public const UNKNOWN_EXCEPTION = 0;

    public const NOT_TIMESTAMPED = 100;
    public const NOT_VERSIONED = 101;

    public const MULTIPLE_SEARCH_INTERFACE = 400;
    public const SEARCH_INTERFACE_NOT_FOUND = 404;

    public function __construct($message, $code = null, $previous = null)
    {
        if ($code === null) {
            $code = self::UNKNOWN_EXCEPTION;
        }
        parent::__construct($message, $code, $previous);
    }
}
