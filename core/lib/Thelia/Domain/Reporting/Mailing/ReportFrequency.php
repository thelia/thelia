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

namespace Thelia\Domain\Reporting\Mailing;

/**
 * How often a report is sent: daily, weekly (on a day of the week) or monthly (on a day
 * of the month, 1 to 28 so every month has it), always from an hour of the day.
 */
enum ReportFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * The latest moment of the schedule that is not after $now.
     */
    public function latestOccurrence(\DateTimeImmutable $now, int $sendHour, ?int $dayOfWeek, ?int $dayOfMonth): \DateTimeImmutable
    {
        $today = $now->setTime($sendHour, 0);

        $occurrence = match ($this) {
            self::Daily => $today,
            self::Weekly => $today->modify(\sprintf('-%d days', (((int) $today->format('N')) - ($dayOfWeek ?? 1) + 7) % 7)),
            self::Monthly => $this->monthlyOccurrence($today, $dayOfMonth ?? 1),
        };

        if ($occurrence > $now) {
            return match ($this) {
                self::Daily => $occurrence->modify('-1 day'),
                self::Weekly => $occurrence->modify('-7 days'),
                self::Monthly => $this->monthlyOccurrence($occurrence->modify('first day of last month')->setTime($sendHour, 0), $dayOfMonth ?? 1),
            };
        }

        return $occurrence;
    }

    private function monthlyOccurrence(\DateTimeImmutable $inTheMonth, int $dayOfMonth): \DateTimeImmutable
    {
        return $inTheMonth->setDate((int) $inTheMonth->format('Y'), (int) $inTheMonth->format('n'), $dayOfMonth);
    }
}
