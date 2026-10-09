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

namespace Thelia\Tests\Unit\Domain\Order\Reminder;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Order\Reminder\InvalidReminderScheduleException;
use Thelia\Domain\Order\Reminder\UnpaidOrderReminderSchedule;

final class UnpaidOrderReminderScheduleTest extends TestCase
{
    public function testAnEmptySettingIsAnEmptySchedule(): void
    {
        self::assertTrue(UnpaidOrderReminderSchedule::fromSetting('')->isEmpty());
        self::assertTrue(UnpaidOrderReminderSchedule::fromSetting('  ')->isEmpty());
        self::assertNull(UnpaidOrderReminderSchedule::fromSetting('')->firstDelayInHours());
    }

    public function testStepsAreReadInTheOrderOfTheirDelay(): void
    {
        $schedule = UnpaidOrderReminderSchedule::fromSetting('72:payment_reminder_2, 24:payment_reminder,168:cancel');

        self::assertSame([24, 72, 168], array_map(static fn ($step): int => $step->delayInHours, $schedule->steps()));
        self::assertSame('payment_reminder', $schedule->steps()[0]->messageCode);
        self::assertTrue($schedule->steps()[2]->isCancellation());
        self::assertSame(24, $schedule->firstDelayInHours());
        self::assertSame('24:payment_reminder,72:payment_reminder_2,168:cancel', $schedule->toSetting());
    }

    public function testTheStepAnOrderIsInIsTheLastOneItReached(): void
    {
        $schedule = UnpaidOrderReminderSchedule::fromSetting('24:payment_reminder,72:payment_reminder,168:cancel');

        self::assertNull($schedule->stepReachedAfter(23));
        self::assertSame(24, $schedule->stepReachedAfter(24)?->delayInHours);
        self::assertSame(24, $schedule->stepReachedAfter(71)?->delayInHours);
        self::assertSame(72, $schedule->stepReachedAfter(100)?->delayInHours);
        self::assertSame(168, $schedule->stepReachedAfter(24 * 60)?->delayInHours);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSettings(): iterable
    {
        yield 'no delay' => ['payment_reminder'];
        yield 'a delay that is not a whole number of hours' => ['1.5:payment_reminder'];
        yield 'a delay of zero' => ['0:payment_reminder'];
        yield 'the same delay twice' => ['24:payment_reminder,24:other_reminder'];
        yield 'no message' => ['24:'];
        yield 'a message code with odd characters' => ['24:payment reminder'];
        yield 'a step after the cancellation' => ['24:cancel,72:payment_reminder'];
        yield 'two cancellations' => ['24:cancel,72:cancel'];
        yield 'too many steps' => ['1:a,2:b,3:c,4:d,5:e,6:f,7:g,8:h,9:i,10:j,11:k'];
    }

    #[DataProvider('invalidSettings')]
    public function testASettingThatIsNotAScheduleIsRefused(string $setting): void
    {
        $this->expectException(InvalidReminderScheduleException::class);

        UnpaidOrderReminderSchedule::fromSetting($setting);
    }
}
