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

namespace Thelia\Tests\Unit\Domain\Subscription;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Subscription\Enum\SubscriptionStatus;

/**
 * The map of the states a subscription goes through, as the back office and the account
 * pages read it to offer an action.
 */
final class SubscriptionStatusTest extends TestCase
{
    public function testTheMapLeadsWhereTheLifeOfASubscriptionGoes(): void
    {
        $map = [];

        foreach (SubscriptionStatus::cases() as $from) {
            $map[$from->value] = array_map(static fn (SubscriptionStatus $to): string => $to->value, $from->next());
        }

        self::assertSame([
            'trial' => ['active', 'payment_failed', 'cancelled'],
            'active' => ['payment_failed', 'paused', 'cancelled'],
            'payment_failed' => ['active', 'suspended', 'cancelled'],
            'suspended' => ['active', 'cancelled'],
            'paused' => ['active', 'cancelled'],
            'cancelled' => ['ended'],
            'ended' => [],
        ], $map);
    }

    public function testOnlyATrialAnActiveOrAFailedSubscriptionIsBilledAndOnlyAnEndedOneIsFinal(): void
    {
        $billable = array_values(array_filter(SubscriptionStatus::cases(), static fn (SubscriptionStatus $status): bool => $status->isBillable()));
        $final = array_values(array_filter(SubscriptionStatus::cases(), static fn (SubscriptionStatus $status): bool => $status->isFinal()));

        self::assertSame([SubscriptionStatus::Trial, SubscriptionStatus::Active, SubscriptionStatus::PaymentFailed], $billable);
        self::assertSame([SubscriptionStatus::Ended], $final);
    }
}
