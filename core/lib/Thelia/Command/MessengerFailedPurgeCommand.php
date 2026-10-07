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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Messenger\FailedMessagePurger;

#[AsCommand(name: 'thelia:messenger:purge-failed', description: 'Delete the jobs set aside in the failure transport for longer than a number of days.')]
class MessengerFailedPurgeCommand extends ContainerAwareCommand
{
    public function __construct(private readonly FailedMessagePurger $purger)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'A job that failed every attempt is kept in the failure transport, with everything it was '
                ."dispatched with, until someone replays or removes it (messenger:failed:show, :retry, :remove).\n"
                .'This deletes those set aside for longer than --older-than days, so the transport does not keep '
                .'personal data forever. Run it every day; use --dry-run to count them first.',
            )
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Age in days from which a failed job is deleted', (string) FailedMessagePurger::RETENTION_DAYS)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count the jobs that would be deleted, and delete nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = $input->getOption('older-than');

        // Zero would delete the failures of this very minute, and a figure past ten
        // years is a typing mistake.
        if (!\is_string($days) || !ctype_digit($days) || (int) $days < 1 || (int) $days > 3650) {
            $output->writeln('<error>--older-than takes a whole number of days, from 1 to 3650.</error>');

            return self::INVALID;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $count = $this->purger->purgeSetAsideBefore(new \DateTimeImmutable(\sprintf('-%d days', (int) $days)), $dryRun);

        $output->writeln(\sprintf(
            $dryRun ? '<info>%d failed job(s) set aside for more than %d day(s) would be deleted.</info>' : '<info>%d failed job(s) set aside for more than %d day(s) deleted.</info>',
            $count,
            (int) $days,
        ));

        return self::SUCCESS;
    }
}
