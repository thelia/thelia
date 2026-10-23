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

namespace Thelia\Domain\Payment\DTO;

use Thelia\Domain\Payment\Enum\PaymentTransactionState;

/**
 * What a payment module answers when the core asks it to capture or to void.
 *
 * The module reports the outcome and the reference the provider gave the movement;
 * the core writes the journal line. A module that hands the call to the provider and
 * will only learn the outcome from a later notification answers pending, and settles
 * the line itself through the recorder when that notification comes.
 */
final readonly class PaymentOperationResult
{
    private function __construct(
        public PaymentTransactionState $state,
        public ?string $pspReference = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {
    }

    public static function succeeded(?string $pspReference = null): self
    {
        return new self(PaymentTransactionState::SUCCEEDED, $pspReference);
    }

    public static function pending(?string $pspReference = null): self
    {
        return new self(PaymentTransactionState::PENDING, $pspReference);
    }

    public static function failed(?string $errorCode, ?string $errorMessage, ?string $pspReference = null): self
    {
        return new self(PaymentTransactionState::FAILED, $pspReference, $errorCode, $errorMessage);
    }
}
