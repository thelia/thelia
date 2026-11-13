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
 * What a customer or an administrator may do to a subscription, and in which state.
 *
 * The one place the rule lives: SubscriptionLifecycle refuses a gesture the rule does not
 * allow, and a theme offers the gestures allowedIn() lists, so that what is offered and what
 * is accepted cannot drift apart.
 *
 *   pause          an active subscription, when its plan allows it
 *   resume         a paused one, and nothing else: a suspended subscription comes back by
 *                  paying what is due (reactivate), a trial ends with its first charge
 *   reactivate     a subscription whose installment failed or that was suspended for it
 *   cancel         whatever the state machine lets become cancelled
 *   payment_method any subscription still billed but a cancelled one
 */
enum SubscriptionAction: string
{
    case Pause = 'pause';
    case Resume = 'resume';
    case Reactivate = 'reactivate';
    case Cancel = 'cancel';
    case ChangePaymentMethod = 'payment_method';

    /**
     * The gestures a subscription in this state allows, in the order they are offered.
     *
     * @return list<self>
     */
    public static function allowedIn(SubscriptionStatus $status, bool $pausable): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $action): bool => $action->isAllowedIn($status, $pausable),
        ));
    }

    public function isAllowedIn(SubscriptionStatus $status, bool $pausable): bool
    {
        return match ($this) {
            self::Pause => $pausable && SubscriptionStatus::Active === $status,
            self::Resume => SubscriptionStatus::Paused === $status,
            self::Reactivate => SubscriptionStatus::Suspended === $status || SubscriptionStatus::PaymentFailed === $status,
            self::Cancel => $status->canBecome(SubscriptionStatus::Cancelled),
            self::ChangePaymentMethod => !$status->isFinal() && SubscriptionStatus::Cancelled !== $status,
        };
    }
}
