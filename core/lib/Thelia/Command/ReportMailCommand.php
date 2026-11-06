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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Domain\Reporting\Mailing\ImmediateReportSender;
use Thelia\Model\ReportMailingQuery;

/**
 * Sends one report now, whatever its schedule says, over the period relative to today.
 *
 * The output holds ids and counts only, as the one of report:send-due.
 */
#[AsCommand(name: 'report:mail', description: 'Send a report mailing now, over the period relative to today.')]
class ReportMailCommand extends ContainerAwareCommand
{
    public function __construct(
        private readonly ImmediateReportSender $sender,
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

        $result = $this->sender->send($mailing);

        if (null === $result) {
            $output->writeln(\sprintf('<comment>Mailing #%d is being sent by another run, nothing was sent: try again in a few minutes.</comment>', $id));

            return self::FAILURE;
        }

        $output->writeln(\sprintf('Mailing #%d: %d sent, %d failed, %d rejected.', $id, \count($result->delivered), \count($result->failed), \count($result->rejected)));

        return [] === $result->failed && [] === $result->rejected ? self::SUCCESS : self::FAILURE;
    }
}
