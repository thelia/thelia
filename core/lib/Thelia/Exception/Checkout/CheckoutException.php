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

namespace Thelia\Exception\Checkout;

use RuntimeException;
use Thelia\Core\Translation\Translator;
use Throwable;

abstract class CheckoutException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 0, Throwable $previous = null)
    {
        $message = Translator::getInstance()->trans($message);
        parent::__construct($message, $code, $previous);
    }
}
