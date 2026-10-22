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
 * Keeps the dates in step with `visible` when something else than the publication
 * service writes the flag: the old toggle and update events, the admin API, a module.
 *
 * A row taken offline that way kept its past publication date and no withdrawal date,
 * and so read as "scheduled, overdue": the scheduled command then put it back online.
 * Asked from `preSave()` of the content and the folder, on a change of the flag of an
 * existing row, it writes what the service would have written. A row the service
 * schedules (hidden, with a future publication date) is left alone.
 *
 * A row turned on ahead of its schedule went online now, not at the date it was
 * waiting for: the date is brought back to now. Kept as it was, the row hidden again an
 * hour later would have read as "scheduled" and the command would have put it back
 * online on the day the schedule named, against the person who hid it.
 */
final readonly class VisibilityDatesAligner
{
    public static function alignOn(PublishableInterface $row, \DateTimeInterface $now): void
    {
        $now = PublicationMoment::atTheSecond($now);
        $publishAt = PublicationMoment::atTheSecondOrNull($row->getPublishAt());
        $unpublishAt = PublicationMoment::atTheSecondOrNull($row->getUnpublishAt());

        if (1 === (int) $row->getVisible()) {
            if (null === $publishAt || $publishAt > $now) {
                $row->setPublishAt($now);
            }

            if (null !== $unpublishAt && $unpublishAt <= $now) {
                $row->setUnpublishAt(null);
            }

            return;
        }

        // Hidden with a publication date still ahead: a schedule, which carries its own dates.
        if (null !== $publishAt && $publishAt > $now) {
            return;
        }

        if (null === $unpublishAt || $unpublishAt > $now) {
            $row->setUnpublishAt($now);
        }
    }
}
