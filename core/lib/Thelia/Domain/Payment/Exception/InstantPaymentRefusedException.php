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

namespace Thelia\Domain\Payment\Exception;

use Thelia\Domain\Checkout\Exception\InvalidPaymentException;

/**
 * An instant payment module refused to settle its part of an order. What was settled
 * before it has been given back, the order is still unpaid and can be paid again. The
 * message is the core's own: no amount and nothing the module said, so it can be answered
 * as is; the cause is the previous exception, for the log.
 */
final class InstantPaymentRefusedException extends InvalidPaymentException
{
    public static function by(\Throwable $cause): self
    {
        return new self(
            'A payment means used for this order refused the payment. Nothing was charged: choose another payment means or try again.',
            0,
            $cause,
        );
    }
}
