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

use Thelia\Domain\Checkout\Enum\CheckoutViolationCode;

/**
 * This very cart is being ordered by another request right now.
 *
 * Refusing is the only safe answer: the request that holds the placement may be inside a
 * payment module, and there is no telling from here whether it will end with an order or
 * with nothing. Creating a second order to avoid saying "wait" is how a buyer ends up
 * charged twice. The caller retries, and by then there is an order to hand back.
 */
final class CheckoutPlacementInProgressException extends CheckoutException
{
    public function __construct(int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct('This order is already being placed. Please wait a moment and try again.', $code, $previous);
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::CheckoutRefused->value;
    }
}
