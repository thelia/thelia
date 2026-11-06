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
 * How the file of a report reaches its recipients.
 */
enum ReportDeliveryMode: string
{
    /** The file is attached to each e-mail. */
    case Attached = 'attached';

    /** The file stays on the server and the e-mail carries a signed, expiring link. */
    case Linked = 'linked';

    /** The period holds no data: the e-mail says so and carries no file. */
    case None = 'none';
}
