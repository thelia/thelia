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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What decides when a report is due, with no database in it.
 */
#[Exclude]
final readonly class ReportSchedule
{
    /**
     * @param list<string> $pendingRecipients the recipients the last send failed for
     * @param int          $pendingAttempts   how many times the period has been tried again since its first send
     *
     * @throws \InvalidArgumentException when the hour or the day is outside what a schedule can honour
     */
    public function __construct(
        public ReportFrequency $frequency,
        public ReportPeriod $period,
        public int $sendHour,
        public ?int $dayOfWeek,
        public ?int $dayOfMonth,
        public ?\DateTimeImmutable $lastPeriodEnd,
        public array $pendingRecipients = [],
        public int $pendingAttempts = 0,
    ) {
        if ($sendHour < 0 || $sendHour > 23) {
            throw new \InvalidArgumentException(\sprintf('The hour of a report schedule is 0 to 23, %d given.', $sendHour));
        }

        if (null !== $dayOfWeek && ($dayOfWeek < 1 || $dayOfWeek > 7)) {
            throw new \InvalidArgumentException(\sprintf('The day of the week of a report schedule is 1 (Monday) to 7 (Sunday), %d given.', $dayOfWeek));
        }

        if (null !== $dayOfMonth && ($dayOfMonth < 1 || $dayOfMonth > 28)) {
            throw new \InvalidArgumentException(\sprintf('The day of the month of a report schedule is 1 to 28, %d given.', $dayOfMonth));
        }

        if ($pendingAttempts < 0) {
            throw new \InvalidArgumentException('The attempts of a report schedule cannot be negative.');
        }
    }
}
