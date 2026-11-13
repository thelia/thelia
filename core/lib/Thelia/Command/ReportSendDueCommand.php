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

namespace Thelia\Command;

use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Domain\Reporting\Mailing\ReportDueResolver;
use Thelia\Domain\Reporting\Mailing\ReportMailer;
use Thelia\Domain\Reporting\Mailing\ReportMailingLock;
use Thelia\Exception\UserFacingFailure;
use Thelia\Log\Tlog;
use Thelia\Model\ReportMailingQuery;

/**
 * Sends the reports that are due.
 *
 * The output holds ids and counts only: the scheduler keeps what a command prints, and
 * a report is made of amounts, names and addresses.
 */
#[AsCommand(name: 'report:send-due', description: 'Send the report mailings that are due, each period once.')]
class ReportSendDueCommand extends ContainerAwareCommand
{
    public function __construct(
        private readonly ReportMailer $reportMailer,
        private readonly ReportDueResolver $dueResolver,
        private readonly ReportMailingLock $lock,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'Run it every few minutes from the scheduler. A period is sent once to every recipient; the recipients '
                .'a send failed for are tried again by the next runs, on their own. It exits with 1 when a mailing '
                .'could not be sent to everyone, so the scheduler can alert.',
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Tell which mailings are due, without sending nor writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $failures = 0;

        $this->initRequest();

        foreach (ReportMailingQuery::create()->filterByEnabled(1)->orderById()->find() as $mailing) {
            $id = (int) $mailing->getId();
            $now = new \DateTimeImmutable($this->clock->now()->format('Y-m-d H:i:s'));

            try {
                $schedule = $mailing->getSchedule();

                if (null === $this->dueResolver->resolve($schedule, $now)) {
                    continue;
                }

                if ($dryRun) {
                    $output->writeln(\sprintf('Mailing #%d is due (dry run, nothing is sent).', $id));

                    continue;
                }

                // A mailing is sent by one process at a time, a send asked from the back office included.
                $lock = $this->lock->acquire($id);

                if (null === $lock) {
                    $output->writeln(\sprintf('<comment>Mailing #%d is being sent by another run, skipped.</comment>', $id));

                    continue;
                }

                try {
                    // Another run may have sent it between the first look and the lock.
                    $mailing->reload();
                    $scheduleAfterTheLock = $mailing->getSchedule();
                    $due = $this->dueResolver->resolve($scheduleAfterTheLock, $now);

                    if (null === $due) {
                        continue;
                    }

                    // The period is noted before the first mail and the waiting list shrinks with each
                    // recipient served: a run killed midway leaves the next one only those still to serve.
                    // A retry goes only to the addresses that are still recipients: one the merchant
                    // removed after a failed send is not sent the figures.
                    $waiting = null === $due->onlyRecipients ? $mailing->getRecipientList() : $mailing->keepCurrentRecipients($due->onlyRecipients);

                    if (null !== $due->onlyRecipients && [] === $waiting) {
                        $mailing->setPendingRecipientList([])->save();

                        continue;
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

                $output->writeln(\sprintf('Mailing #%d: %d sent, %d failed, %d rejected.', $id, \count($result->delivered), \count($result->failed), \count($result->rejected)));

                if ($abandoned > 0) {
                    $this->warnGivenUp($id, $abandoned);
                }

                if ([] !== $result->rejected) {
                    // The count and the mailing only: an address is personal data, and is dropped from the retries.
                    Tlog::getInstance()->addWarning(\sprintf('Report mailing #%d: %d recipient address(es) are not valid e-mail addresses and will not be tried again.', $id, \count($result->rejected)));
                }

                if ([] !== $result->failed || [] !== $result->rejected) {
                    ++$failures;
                }
            } catch (\Throwable $notSent) {
                ++$failures;
                // The message of an exception meant for the administrator says what to fix; any other one may quote SQL, values or addresses, so only its type is printed.
                $output->writeln(\sprintf('<error>Mailing #%d could not be sent (%s).</error>', $id, $notSent instanceof UserFacingFailure ? $notSent->getMessage() : $notSent::class));
            }
        }

        return 0 === $failures ? self::SUCCESS : self::FAILURE;
    }

    private function warnGivenUp(int $mailingId, int $recipients): void
    {
        // The count and the mailing only: the addresses stay out of the logs.
        Tlog::getInstance()->addWarning(\sprintf('Report mailing #%d: %d recipient(s) still failing after %d tries are given up for this period.', $mailingId, $recipients, ReportDueResolver::MAX_ATTEMPTS));
    }
}
