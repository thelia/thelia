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
 * One step of the reminder schedule: once an unpaid order is this many hours old, it is
 * sent the e-mail of that message, or cancelled when the step has no message.
 */
final readonly class UnpaidOrderReminderStep
{
    public const CANCELLATION = 'cancel';

    public function __construct(
        public int $delayInHours,
        public ?string $messageCode,
    ) {
    }

    public function isCancellation(): bool
    {
        return null === $this->messageCode;
    }

    public function toSetting(): string
    {
        return $this->delayInHours.':'.($this->messageCode ?? self::CANCELLATION);
    }
}
