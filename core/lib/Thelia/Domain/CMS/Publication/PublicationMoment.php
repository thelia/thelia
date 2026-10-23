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

namespace Thelia\Domain\CMS\Publication;

/**
 * The moment a publication date is written or compared at: in the timezone of PHP, at
 * the second, never finer.
 *
 * A `DATETIME` column keeps no fraction of a second, and MySQL rounds one it is given
 * where MariaDB truncates it: a withdrawal written at 12:00:00.7 is stored at 12:00:01,
 * and the row reads as "scheduled" for the rest of that second, which is long enough for
 * the next statement to see it. Nor does the column keep a timezone: a date is stored as
 * the wall clock it is formatted with, and every "now" the publication compares against
 * is the wall clock of PHP, so a date given in another zone (`09:00:00+00:00` sent to
 * the API, by a shop whose PHP runs on Paris time) is brought to that zone first. Every
 * date the publication writes, and every "now" it compares a state against, goes
 * through here.
 */
final readonly class PublicationMoment
{
    public static function atTheSecond(\DateTimeInterface $moment): \DateTimeImmutable
    {
        $moment = \DateTimeImmutable::createFromInterface($moment)->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        return $moment->setTime((int) $moment->format('G'), (int) $moment->format('i'), (int) $moment->format('s'));
    }

    /**
     * @param string|int|\DateTimeInterface|null $moment an integer is a Unix timestamp, as
     *                                                   the Propel setters accept one; an
     *                                                   empty string is no date, as for them,
     *                                                   not the "now" PHP would read it as
     */
    public static function atTheSecondOrNull(string|int|\DateTimeInterface|null $moment): ?\DateTimeImmutable
    {
        if (null === $moment || '' === $moment) {
            return null;
        }

        if (\is_int($moment)) {
            $moment = (new \DateTimeImmutable())->setTimestamp($moment);
        }

        return self::atTheSecond(\is_string($moment) ? new \DateTimeImmutable($moment) : $moment);
    }
}
