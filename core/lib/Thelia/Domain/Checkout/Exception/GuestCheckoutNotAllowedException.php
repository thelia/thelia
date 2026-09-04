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

namespace Thelia\Domain\Checkout\Exception;

/**
 * The shop no longer lets this cart be ordered without an account.
 *
 * Raised when the buyer identified themselves as a guest and the answer changed
 * underneath them: a product that requires an account was added to the cart after the
 * identification, or the shop turned the guest checkout off in the meantime.
 */
class GuestCheckoutNotAllowedException extends CheckoutException
{
    public function __construct(string $message = 'This order requires an account.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
