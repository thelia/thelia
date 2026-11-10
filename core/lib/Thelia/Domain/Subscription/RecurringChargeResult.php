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

namespace Thelia\Domain\Subscription;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Subscription\Enum\RecurringChargeOutcome;

/**
 * What a payment module answers to an off-session charge.
 *
 * The reason is meant for the merchant and the logs: never a token nor a card number.
 */
#[Exclude]
final readonly class RecurringChargeResult
{
    private function __construct(
        public RecurringChargeOutcome $outcome,
        public ?string $transactionRef = null,
        public ?string $reason = null,
    ) {
    }

    public static function paid(string $transactionRef): self
    {
        return new self(RecurringChargeOutcome::Paid, $transactionRef);
    }

    public static function declined(string $reason): self
    {
        return new self(RecurringChargeOutcome::Declined, null, $reason);
    }

    public static function actionRequired(string $reason): self
    {
        return new self(RecurringChargeOutcome::ActionRequired, null, $reason);
    }

    public static function retryable(string $reason): self
    {
        return new self(RecurringChargeOutcome::Retryable, null, $reason);
    }

    public function isPaid(): bool
    {
        return RecurringChargeOutcome::Paid === $this->outcome;
    }
}
