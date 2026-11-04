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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Domain\Reporting\Mailing\ReportMailer;
use Thelia\Model\ReportMailingQuery;

/**
 * Sends one report now, whatever its schedule says, over the period relative to today.
 * What was sent for the schedule is left alone: only the date of the last send moves.
 *
 * The output holds ids and counts only, as the one of report:send-due.
 */
#[AsCommand(name: 'report:mail', description: 'Send a report mailing now, over the period relative to today.')]
class ReportMailCommand extends ContainerAwareCommand
{
    public function __construct(
        private readonly ReportMailer $reportMailer,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Id of the report mailing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (int) $input->getArgument('id');
        $mailing = ReportMailingQuery::create()->findPk($id);

        if (null === $mailing) {
            $output->writeln(\sprintf('<error>There is no mailing #%d.</error>', $id));

            return self::FAILURE;
        }

        $this->initRequest();

        $now = new \DateTimeImmutable($this->clock->now()->format('Y-m-d H:i:s'));
        $result = $this->reportMailer->send($mailing, $now);

        $mailing->setLastSentAt($now)->save();

        $output->writeln(\sprintf('Mailing #%d: %d sent, %d failed, %d rejected.', $id, \count($result->delivered), \count($result->failed), \count($result->rejected)));

        return [] === $result->failed && [] === $result->rejected ? self::SUCCESS : self::FAILURE;
    }
}
