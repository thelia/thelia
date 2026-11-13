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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Subscription\Enum\SubscriptionAction;
use Thelia\Domain\Subscription\Enum\SubscriptionStatus;

final class SubscriptionActionTest extends TestCase
{
    /**
     * @return iterable<string, array{SubscriptionStatus, bool, list<SubscriptionAction>}>
     */
    public static function gestures(): iterable
    {
        yield 'a trial is cancelled or given another method, never resumed' => [SubscriptionStatus::Trial, true, [SubscriptionAction::Cancel, SubscriptionAction::ChangePaymentMethod]];
        yield 'an active subscription is paused when its plan allows it' => [SubscriptionStatus::Active, true, [SubscriptionAction::Pause, SubscriptionAction::Cancel, SubscriptionAction::ChangePaymentMethod]];
        yield 'an active subscription of a plan that cannot pause' => [SubscriptionStatus::Active, false, [SubscriptionAction::Cancel, SubscriptionAction::ChangePaymentMethod]];
        yield 'a paused subscription is resumed' => [SubscriptionStatus::Paused, true, [SubscriptionAction::Resume, SubscriptionAction::Cancel, SubscriptionAction::ChangePaymentMethod]];
        yield 'a failed installment is charged again, never resumed' => [SubscriptionStatus::PaymentFailed, true, [SubscriptionAction::Reactivate, SubscriptionAction::Cancel, SubscriptionAction::ChangePaymentMethod]];
        yield 'a suspended subscription is reactivated, never resumed' => [SubscriptionStatus::Suspended, true, [SubscriptionAction::Reactivate, SubscriptionAction::Cancel, SubscriptionAction::ChangePaymentMethod]];
        yield 'a cancelled subscription waits for its end' => [SubscriptionStatus::Cancelled, true, []];
        yield 'an ended subscription is final' => [SubscriptionStatus::Ended, true, []];
    }

    /**
     * @param list<SubscriptionAction> $expected
     */
    #[DataProvider('gestures')]
    public function testTheGesturesAStateAllows(SubscriptionStatus $status, bool $pausable, array $expected): void
    {
        self::assertSame($expected, SubscriptionAction::allowedIn($status, $pausable));
    }

    public function testOnlyAPausedSubscriptionIsResumed(): void
    {
        foreach (SubscriptionStatus::cases() as $status) {
            self::assertSame(SubscriptionStatus::Paused === $status, SubscriptionAction::Resume->isAllowedIn($status, true), $status->value);
        }
    }

    public function testACancelOfferedIsACancelTheStateMachineAccepts(): void
    {
        foreach (SubscriptionStatus::cases() as $status) {
            self::assertSame($status->canBecome(SubscriptionStatus::Cancelled), SubscriptionAction::Cancel->isAllowedIn($status, true), $status->value);
        }
    }
}
