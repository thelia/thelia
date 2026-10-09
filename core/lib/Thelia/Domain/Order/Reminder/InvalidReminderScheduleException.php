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

namespace Thelia\Domain\Order\Reminder;

/**
 * A reminder schedule that cannot be read: the setting is refused as a whole, never half
 * applied.
 */
final class InvalidReminderScheduleException extends \InvalidArgumentException
{
}
