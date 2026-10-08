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
 * What the lines of an order's payment journal add up to, as DECIMAL strings.
 *
 * The succeeded lines are what happened. A pending capture, void or refund is what
 * may already have happened at the provider, whose answer has not come back: it is not
 * reported as captured, voided or refunded, but what it asked for is out of reach of
 * the next movement, so two captures can never both take what one authorization holds.
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
        public string $pendingCapture = '0.000000',
        public string $pendingVoid = '0.000000',
        public string $pendingRefund = '0.000000',
    ) {
        $committed = PaymentAmount::add(PaymentAmount::add($captured, $pendingCapture), PaymentAmount::add($voided, $pendingVoid));
        $remaining = PaymentAmount::subtract($authorized, $committed);

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

    public function hasPendingCapture(): bool
    {
        return PaymentAmount::isPositive($this->pendingCapture);
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

    /**
     * What a new refund may still give back, once the refunds awaiting their answer
     * are counted as given.
     */
    public function refundable(): string
    {
        $refundable = PaymentAmount::subtract($this->netCaptured(), $this->pendingRefund);

        return PaymentAmount::isPositive($refundable) ? $refundable : PaymentAmount::normalize(0);
    }
}
