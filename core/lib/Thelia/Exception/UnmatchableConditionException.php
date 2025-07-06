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

use Thelia\Core\Translation\Translator;

class UnmatchableConditionException extends \RuntimeException
{
    public function __construct(?string $message = null, int $code = 0, ?\Throwable $previous = null)
    {
        if (null === $message) {
            $message = Translator::getInstance()->trans('Coupon conditions cannot be verified.');
        }

        parent::__construct($message, $code, $previous);
    }

    public static function getMissingCustomerMessage(): string
    {
        return Translator::getInstance()->trans('You must sign in or register before using this coupon.');
    }
}
