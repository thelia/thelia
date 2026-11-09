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
use Thelia\Domain\Reporting\Mailing\DueReportSender;
use Thelia\Domain\Reporting\Mailing\DueReportSkip;
use Thelia\Domain\Reporting\Mailing\ReportDueResolver;
use Thelia\Domain\Reporting\Mailing\ReportMailer;
use Thelia\Domain\Reporting\Mailing\ReportMailingLock;
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
    private readonly DueReportSender $dueReportSender;

    public function __construct(
        ReportMailer $reportMailer,
        private readonly ReportDueResolver $dueResolver,
        ReportMailingLock $lock,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();

        $this->dueReportSender = new DueReportSender($reportMailer, $dueResolver, $lock);
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

                $result = $this->dueReportSender->send($mailing, $now);

                if (DueReportSkip::Locked === $result) {
                    $output->writeln(\sprintf('<comment>Mailing #%d is being sent by another run, skipped.</comment>', $id));

                    continue;
                }

                if (DueReportSkip::NoLongerDue === $result) {
                    continue;
                }

                $output->writeln(\sprintf('Mailing #%d: %d sent, %d failed, %d rejected.', $id, \count($result->delivered), \count($result->failed), \count($result->rejected)));

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
