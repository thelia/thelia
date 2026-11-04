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
use Symfony\Component\Lock\LockFactory;
use Thelia\Domain\Reporting\Mailing\ReportDueResolver;
use Thelia\Domain\Reporting\Mailing\ReportMailer;
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
    /**
     * A mailing is sent by one run at a time: the second one skips it and says so. The
     * lock dies with its process, and with a store that honours it, after this many
     * seconds should the process hang.
     */
    private const float LOCK_TTL_SECONDS = 900.0;

    public function __construct(
        private readonly ReportMailer $reportMailer,
        private readonly ReportDueResolver $dueResolver,
        private readonly LockFactory $lockFactory,
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

                $lock = $this->lockFactory->createLock('report_mailing.'.$id, self::LOCK_TTL_SECONDS);

                if (!$lock->acquire()) {
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
                    $waiting = $due->onlyRecipients ?? $mailing->getRecipientList();
                    $mailing
                        ->setLastPeriodEnd($due->periodEnd->format('Y-m-d'))
                        ->setPendingRecipientList($waiting)
                        ->setPendingAttempts(null === $due->onlyRecipients ? 0 : $due->attempt - 1)
                        ->save();

                    $result = $this->reportMailer->send(
                        $mailing,
                        $due->occurrence,
                        $due->onlyRecipients,
                        static function (string $served) use ($mailing, &$waiting): void {
                            $waiting = array_values(array_diff($waiting, [$served]));
                            $mailing->setPendingRecipientList($waiting)->save();
                        },
                    );

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
                    // The count and the mailing only, here too: the addresses stay out of the logs.
                    Tlog::getInstance()->addWarning(\sprintf('Report mailing #%d: %d recipient(s) still failing after %d tries are given up for this period.', $id, $abandoned, ReportDueResolver::MAX_ATTEMPTS));
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
                $output->writeln(\sprintf('<error>Mailing #%d could not be sent (%s).</error>', $id, $notSent::class));
            }
        }

        return 0 === $failures ? self::SUCCESS : self::FAILURE;
    }
}
