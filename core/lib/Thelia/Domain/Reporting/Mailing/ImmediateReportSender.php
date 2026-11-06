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

use Psr\Clock\ClockInterface;
use Thelia\Model\ReportMailing;

/**
 * Sends one report now, whatever its schedule says, over the period relative to today.
 * What was sent for the schedule is left alone: only the date of the last send moves.
 *
 * It takes the lock of the scheduled runs: a send asked while a run is sending the same
 * mailing is refused rather than mailed twice.
 */
final readonly class ImmediateReportSender
{
    public function __construct(
        private ReportMailer $reportMailer,
        private ReportMailingLock $lock,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return ReportMailingResult|null null when another process is sending the mailing: nothing was sent
     */
    public function send(ReportMailing $mailing): ?ReportMailingResult
    {
        $lock = $this->lock->acquire((int) $mailing->getId());

        if (null === $lock) {
            return null;
        }

        try {
            // To the second: MySQL rounds a fraction up, into the future.
            $now = new \DateTimeImmutable($this->clock->now()->format('Y-m-d H:i:s'));
            $result = $this->reportMailer->send($mailing, $now);
            $mailing->setLastSentAt($now)->save();

            return $result;
        } finally {
            $lock->release();
        }
    }
}
