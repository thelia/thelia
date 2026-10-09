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

final class UnpaidOrderReminderReport
{
    /** @var list<UnpaidOrderReminderOutcome> */
    private array $outcomes = [];

    public function add(UnpaidOrderReminderOutcome $outcome): void
    {
        $this->outcomes[] = $outcome;
    }

    /**
     * @return list<UnpaidOrderReminderOutcome>
     */
    public function outcomes(): array
    {
        return $this->outcomes;
    }

    public function hasFailures(): bool
    {
        foreach ($this->outcomes as $outcome) {
            if (UnpaidOrderReminderOutcome::STATUS_FAILED === $outcome->status) {
                return true;
            }
        }

        return false;
    }
}
