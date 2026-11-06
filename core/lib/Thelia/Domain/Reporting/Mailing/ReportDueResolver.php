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
 * Whether a report is due at a given moment. Nothing is read from the clock or the
 * database: the moment is given.
 *
 * The occurrence is the latest moment of the schedule that is not after now. The report
 * is due when the period resolved for that occurrence ends after the last period sent,
 * or, when that period is the one already sent, when some recipients are still waiting
 * for it, up to MAX_ATTEMPTS tries in all. A schedule never sends a period twice to the same recipient, and never sends
 * an older period once a newer one is due.
 */
final class ReportDueResolver
{
    /**
     * The tries of a period, the first send included: a recipient the server keeps refusing
     * is given up after them, rather than tried every few minutes until the next period.
     */
    public const int MAX_ATTEMPTS = 3;

    public function resolve(ReportSchedule $schedule, \DateTimeImmutable $now): ?DueReport
    {
        $occurrence = $schedule->frequency->latestOccurrence($now, $schedule->sendHour, $schedule->dayOfWeek, $schedule->dayOfMonth);
        ['start' => $start, 'end' => $end] = $schedule->period->resolve($occurrence);

        $lastEnd = $schedule->lastPeriodEnd?->format('Y-m-d');

        if (null === $lastEnd || $end->format('Y-m-d') > $lastEnd) {
            return new DueReport($occurrence, $start, $end, null, 1, self::MAX_ATTEMPTS <= 1);
        }

        if ($end->format('Y-m-d') === $lastEnd && [] !== $schedule->pendingRecipients) {
            $attempt = $schedule->pendingAttempts + 2;

            return new DueReport($occurrence, $start, $end, $schedule->pendingRecipients, $attempt, $attempt >= self::MAX_ATTEMPTS);
        }

        return null;
    }
}
