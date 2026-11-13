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

namespace Thelia\Domain\Subscription;

use Thelia\Domain\CMS\Publication\PublicationMoment;
use Thelia\Domain\Subscription\Enum\PeriodUnit;

/**
 * The dates of a subscription: where a period ends, when a trial ends.
 *
 * A period counted in months ends on the anchor day of the month it lands in, or on the
 * last day of that month when it is shorter, and the next one goes back to the anchor day:
 * anchored on the 31st, 31/01 is followed by 29/02/2028, then 31/03. A week is seven days,
 * a quarter three months, a year twelve.
 *
 * Every date is cut to the second: the DATETIME columns hold whole seconds, and MySQL rounds
 * a fraction where MariaDB truncates it, so a date kept with its microseconds is read back
 * one second later, in the future, on one engine and not on the other.
 */
final readonly class BillingCalendar
{
    public function periodEnd(\DateTimeInterface $start, PeriodUnit $unit, int $count, int $anchorDay): \DateTimeImmutable
    {
        return $this->advance($start, $unit, $count, $anchorDay, 1);
    }

    /**
     * The end of the given number of periods from the start, each one landing on the anchor day.
     */
    public function advance(\DateTimeInterface $start, PeriodUnit $unit, int $count, int $anchorDay, int $periods): \DateTimeImmutable
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('A period lasts one unit at least.');
        }

        $start = PublicationMoment::atTheSecond($start);
        $months = $unit->months();

        if (null === $months) {
            return $start->modify(\sprintf('+%d days', 7 * $count * max(0, $periods)));
        }

        $monthIndex = (int) $start->format('Y') * 12 + (int) $start->format('n') - 1 + $months * $count * max(0, $periods);
        $year = intdiv($monthIndex, 12);
        $month = $monthIndex % 12 + 1;
        $lastDay = (int) $start->setDate($year, $month, 1)->format('t');

        return $start->setDate($year, $month, min(max(1, $anchorDay), $lastDay));
    }

    public function trialEnd(\DateTimeInterface $start, int $trialDays): \DateTimeImmutable
    {
        return PublicationMoment::atTheSecond($start)->modify(\sprintf('+%d days', max(0, $trialDays)));
    }

    /**
     * The anchor day a subscription keeps for good: the day of the month its first paid
     * period starts on.
     */
    public function anchorDayOf(\DateTimeInterface $start): int
    {
        return (int) PublicationMoment::atTheSecond($start)->format('j');
    }
}
