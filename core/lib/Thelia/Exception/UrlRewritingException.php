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

namespace Thelia\Exception;

class UrlRewritingException extends \Exception
{
    public const UNKNOWN_EXCEPTION = 0;

    public const URL_ALREADY_EXISTS = 100;

    public const URL_NOT_FOUND = 404;

    public const RESOLVER_NULL_SEARCH = 800;

    public function __construct($message, $code = null, $previous = null)
    {
        if ($code === null) {
            $code = self::UNKNOWN_EXCEPTION;
        }
        parent::__construct($message, $code, $previous);
    }
}
