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
 * The carrier of the cart offers delivery dates and the buyer has not picked one, or has
 * picked a day without the slot this carrier asks for.
 *
 * A delivery refusal like any other, so every caller that already sends the buyer back to
 * the delivery step on InvalidDeliveryException does it here too.
 */
class DeliveryDateRequiredException extends InvalidDeliveryException
{
    public function __construct(string $message = 'Please choose a delivery date.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::DeliveryDateMissing->value;
    }
}
