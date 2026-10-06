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
 * The slot the buyer picked has no place left that day.
 *
 * Raised before the order when the slot is already full, and inside the order transaction
 * when another buyer took the last place in between: the conditional update that books the
 * place is what arbitrates, and the order is rolled back.
 */
class DeliverySlotFullException extends InvalidDeliveryException
{
    public function __construct(string $message = 'This delivery slot is full. Please choose another one.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function violationCode(): string
    {
        return CheckoutViolationCode::DeliverySlotFull->value;
    }
}
