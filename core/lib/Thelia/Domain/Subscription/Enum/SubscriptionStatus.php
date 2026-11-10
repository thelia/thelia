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

namespace Thelia\Domain\Subscription\Enum;

/**
 * The states of a subscription, stored as their value in subscription.status.
 *
 *   trial          -> active | payment_failed | cancelled
 *   active         -> payment_failed | paused | cancelled
 *   payment_failed -> active | suspended | cancelled
 *   suspended      -> active | cancelled
 *   paused         -> active | cancelled
 *   cancelled      -> ended (end of the paid period)
 *   ended          (final)
 *
 * A cancelled subscription stays served until its cancel date, then ends: it is no longer
 * charged. Only SubscriptionStateMachine moves a subscription from one state to another.
 */
enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PaymentFailed = 'payment_failed';
    case Suspended = 'suspended';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Ended = 'ended';

    /**
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Trial => [self::Active, self::PaymentFailed, self::Cancelled],
            self::Active => [self::PaymentFailed, self::Paused, self::Cancelled],
            self::PaymentFailed => [self::Active, self::Suspended, self::Cancelled],
            self::Suspended => [self::Active, self::Cancelled],
            self::Paused => [self::Active, self::Cancelled],
            self::Cancelled => [self::Ended],
            self::Ended => [],
        };
    }

    public function canBecome(self $target): bool
    {
        return \in_array($target, $this->next(), true);
    }

    /** Whether the shop may charge an installment in this state. */
    public function isBillable(): bool
    {
        return \in_array($this, [self::Trial, self::Active, self::PaymentFailed], true);
    }

    public function isFinal(): bool
    {
        return self::Ended === $this;
    }
}
