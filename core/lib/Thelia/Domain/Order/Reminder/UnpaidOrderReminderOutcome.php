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
 * What a run did, or would do on a dry run, to one order.
 */
final readonly class UnpaidOrderReminderOutcome
{
    public const ACTION_REMIND = 'remind';
    public const ACTION_CANCEL = 'cancel';

    public const STATUS_DONE = 'done';
    public const STATUS_PLANNED = 'planned';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        public int $orderId,
        public string $orderRef,
        public int $delayInHours,
        public string $action,
        public string $status,
        public ?string $error = null,
    ) {
    }
}
