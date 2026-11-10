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

use Thelia\Domain\Subscription\Enum\SubscriptionStatus;

/**
 * A change of status the subscription cannot take: the map of SubscriptionStatus does not
 * lead there, or another process moved the subscription since it was read.
 */
final class SubscriptionTransitionRefusedException extends SubscriptionException
{
    public static function notAllowed(SubscriptionStatus $from, SubscriptionStatus $to): self
    {
        return new self(\sprintf('A subscription cannot go from "%s" to "%s".', $from->value, $to->value));
    }

    public static function movedMeanwhile(SubscriptionStatus $expected): self
    {
        return new self(\sprintf('The subscription is no longer "%s": it was changed meanwhile.', $expected->value));
    }
}
