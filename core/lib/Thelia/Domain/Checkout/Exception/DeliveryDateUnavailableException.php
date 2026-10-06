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
 * The day, or the slot, the cart holds is not one the carrier offers: before the first day,
 * after the last one, on a closed day, a slot of another carrier, or a value forged in a
 * request. Judged against what the server computes, never against what the page showed.
 */
class DeliveryDateUnavailableException extends InvalidDeliveryException
{
    public function __construct(string $message = 'This delivery date is not available. Please choose another one.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::DeliveryDateUnavailable->value;
    }
}
