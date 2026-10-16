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

namespace Thelia\Scheduler;

/**
 * The last failure of a recurring task, as the back office lists it.
 */
final readonly class RecurringTaskFailure
{
    public function __construct(
        public string $task,
        public \DateTimeImmutable $failedAt,
        public string $error,
    ) {
    }
}
