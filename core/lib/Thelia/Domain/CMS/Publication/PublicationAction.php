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
 * The gestures a back-office user, the API or the scheduled command make on a row.
 */
enum PublicationAction: string
{
    /** Put the row online now, keeping or setting a future withdrawal date. */
    case Publish = 'publish';

    /** Keep the row hidden until a future date, with an optional later withdrawal. */
    case Schedule = 'schedule';

    /** Forget the pending date: a scheduled row becomes a draft, a published row loses its withdrawal date. */
    case Cancel = 'cancel';

    /** Take the row offline now. */
    case Unpublish = 'unpublish';

    /** Set, or clear, the withdrawal date of a published row. */
    case ScheduleUnpublication = 'schedule_unpublication';
}
