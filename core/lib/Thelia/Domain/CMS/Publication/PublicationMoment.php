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
 * The moment a publication date is written or compared at: the second, never finer.
 *
 * A `DATETIME` column keeps no fraction of a second, and MySQL rounds one it is given
 * where MariaDB truncates it: a withdrawal written at 12:00:00.7 is stored at 12:00:01,
 * and the row reads as "scheduled" for the rest of that second, which is long enough for
 * the next statement to see it. Every date the publication writes, and every "now" it
 * compares a state against, goes through here first.
 */
final readonly class PublicationMoment
{
    public static function atTheSecond(\DateTimeInterface $moment): \DateTimeImmutable
    {
        $moment = \DateTimeImmutable::createFromInterface($moment);

        return $moment->setTime((int) $moment->format('G'), (int) $moment->format('i'), (int) $moment->format('s'));
    }

    public static function atTheSecondOrNull(string|\DateTimeInterface|null $moment): ?\DateTimeImmutable
    {
        if (null === $moment) {
            return null;
        }

        return self::atTheSecond(\is_string($moment) ? new \DateTimeImmutable($moment) : $moment);
    }
}
