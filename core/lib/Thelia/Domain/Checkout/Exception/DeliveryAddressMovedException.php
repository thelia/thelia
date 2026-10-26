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
 * The delivery address was moved to another place after the cart copied it.
 *
 * The cart ships to its own copy of the address, and its carrier and postage were quoted
 * for the place that copy names. Another country, state, postal code or city, written from
 * the customer account or through the API, is a place nobody quoted: the address has to be
 * chosen again on the delivery step, which asks the carriers and their postage again.
 */
class DeliveryAddressMovedException extends InvalidDeliveryException
{
    public function __construct(string $message = 'Your delivery address has changed. Please choose your delivery method again.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::DeliveryAddressMoved->value;
    }
}
