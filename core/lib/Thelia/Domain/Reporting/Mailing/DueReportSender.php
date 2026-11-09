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

use Thelia\Log\Tlog;
use Thelia\Model\ReportMailing;

/**
 * Sends one report on its schedule: the period that is due, to the recipients it is due
 * to, each period once.
 *
 * It takes the lock of the mailing and checks again what is due once it holds it. The
 * period is noted before the first mail and the waiting list shrinks with each recipient
 * served: a run killed midway leaves the next one only those still to serve. The
 * recipients still failing after the last try are given up for the period.
 */
final readonly class DueReportSender
{
    public function __construct(
        private ReportMailer $reportMailer,
        private ReportDueResolver $dueResolver,
        private ReportMailingLock $lock,
    ) {
    }

    public function send(ReportMailing $mailing, \DateTimeImmutable $now): ReportMailingResult|DueReportSkip
    {
        $id = (int) $mailing->getId();

        // A mailing is sent by one process at a time, a send asked from the back office included.
        $lock = $this->lock->acquire($id);

        if (null === $lock) {
            return DueReportSkip::Locked;
        }

        try {
            // Another run may have sent it between the first look and the lock.
            $mailing->reload();
            $due = $this->dueResolver->resolve($mailing->getSchedule(), $now);

            if (null === $due) {
                return DueReportSkip::NoLongerDue;
            }

            // A retry goes only to the addresses that are still recipients: one the merchant
            // removed after a failed send is not sent the figures.
            $waiting = null === $due->onlyRecipients ? $mailing->getRecipientList() : $mailing->keepCurrentRecipients($due->onlyRecipients);

            if (null !== $due->onlyRecipients && [] === $waiting) {
                $mailing->setPendingRecipientList([])->save();

                return DueReportSkip::NoLongerDue;
            }

            $mailing
                ->setLastPeriodEnd($due->periodEnd->format('Y-m-d'))
                ->setPendingRecipientList($waiting)
                ->setPendingAttempts(null === $due->onlyRecipients ? 0 : $due->attempt - 1)
                ->save();

            try {
                $result = $this->reportMailer->send(
                    $mailing,
                    $due->occurrence,
                    $waiting,
                    static function (string $served) use ($mailing, &$waiting): void {
                        $waiting = array_values(array_diff($waiting, [$served]));
                        $mailing->setPendingRecipientList($waiting)->save();
                    },
                );
            } catch (\Throwable $notSent) {
                // A send that fails as a whole (no sender address, export or storage failure) is a
                // try too: the period is given up after the last one, as a refused recipient is.
                if ($due->lastAttempt) {
                    $mailing->setPendingRecipientList([])->save();
                    $this->warnGivenUp($id, \count($waiting));
                }

                throw $notSent;
            }

            $abandoned = $due->lastAttempt ? \count($result->failed) : 0;

            $mailing
                ->setPendingRecipientList($due->lastAttempt ? [] : $result->failed)
                ->setLastSentAt($now)
                ->save();
        } finally {
            $lock->release();
        }

        if ($abandoned > 0) {
            $this->warnGivenUp($id, $abandoned);
        }

        if ([] !== $result->rejected) {
            // The count and the mailing only: an address is personal data, and is dropped from the retries.
            Tlog::getInstance()->addWarning(\sprintf('Report mailing #%d: %d recipient address(es) are not valid e-mail addresses and will not be tried again.', $id, \count($result->rejected)));
        }

        return $result;
    }

    private function warnGivenUp(int $mailingId, int $recipients): void
    {
        // The count and the mailing only: the addresses stay out of the logs.
        Tlog::getInstance()->addWarning(\sprintf('Report mailing #%d: %d recipient(s) still failing after %d tries are given up for this period.', $mailingId, $recipients, ReportDueResolver::MAX_ATTEMPTS));
    }
}
