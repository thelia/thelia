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

namespace Thelia\Domain\CMS\Publication\Exception;

/**
 * The dates of a gesture do not hold: in the wrong order, in the past where a future
 * date is expected, missing, or unreadable.
 */
final class InvalidPublicationDatesException extends PublicationException
{
}
