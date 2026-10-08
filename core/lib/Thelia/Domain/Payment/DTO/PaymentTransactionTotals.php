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

use Thelia\Domain\Payment\Service\PaymentAmount;

/**
 * What the succeeded lines of an order's payment journal add up to, as DECIMAL strings.
 *
 * The amount left to capture only means something once an authorization exists: a
 * module that takes the price at once writes a capture and no authorization, and
 * has nothing left to capture.
 */
final readonly class PaymentTransactionTotals
{
    public string $remainingToCapture;

    public function __construct(
        public string $authorized,
        public string $captured,
        public string $voided,
        public string $refunded,
    ) {
        $remaining = PaymentAmount::subtract(PaymentAmount::subtract($authorized, $voided), $captured);

        $this->remainingToCapture = $this->hasAuthorization() && PaymentAmount::isPositive($remaining)
            ? $remaining
            : PaymentAmount::normalize(0);
    }

    public static function empty(): self
    {
        $zero = PaymentAmount::normalize(0);

        return new self($zero, $zero, $zero, $zero);
    }

    public function hasAuthorization(): bool
    {
        return PaymentAmount::isPositive($this->authorized);
    }

    public function hasSomethingLeftToCapture(): bool
    {
        return PaymentAmount::isPositive($this->remainingToCapture);
    }

    public function allows(string $captureAmount): bool
    {
        return PaymentAmount::compare($captureAmount, $this->remainingToCapture) <= 0;
    }

    /**
     * What was taken and not given back.
     */
    public function netCaptured(): string
    {
        return PaymentAmount::subtract($this->captured, $this->refunded);
    }
}
