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

namespace Thelia\Domain\Subscription\Exception;

use Thelia\Domain\Subscription\Enum\SubscriptionAction;
use Thelia\Domain\Subscription\Enum\SubscriptionStatus;

/**
 * An action on a subscription its plan or its state does not allow.
 */
final class SubscriptionActionRefusedException extends SubscriptionException
{
    public static function notPausable(): self
    {
        return new self('This subscription cannot be paused.');
    }

    public static function notInState(SubscriptionAction $action, SubscriptionStatus $status): self
    {
        return new self(\sprintf('A subscription that is "%s" cannot be %s.', $status->value, match ($action) {
            SubscriptionAction::Pause => 'paused',
            SubscriptionAction::Resume => 'resumed',
            SubscriptionAction::Reactivate => 'reactivated',
            SubscriptionAction::Cancel => 'cancelled',
            SubscriptionAction::ChangePaymentMethod => 'given another payment method',
        }));
    }

    public static function methodOfAnotherModule(): self
    {
        return new self('The installments of this subscription are charged by another payment module than the one of this method.');
    }
}
