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
use Symfony\Component\Lock\LockFactory;
use Thelia\Domain\CMS\Publication\ScheduledPublicationRunner;

#[AsCommand(name: 'content:publish-scheduled', description: 'Put online the contents and folders scheduled for a moment now past, and take offline those whose withdrawal date has come.')]
class ContentPublishScheduledCommand extends ContainerAwareCommand
{
    /**
     * Two runs at once would act on the same rows: the second one waits for nothing and
     * says so. The lock dies with its process, and with a store that honours it, after
     * this many seconds should the process hang; a pass longer than that keeps it alive
     * by refreshing it after each batch.
     */
    private const float LOCK_TTL_SECONDS = 600.0;

    public function __construct(
        protected ScheduledPublicationRunner $runner,
        protected LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'Run it every few minutes from the scheduler of the server. Each row goes through its publication event, '
                .'so the caches are purged like after a back-office change. A content with no body in the default language '
                .'of the shop stays scheduled, is reported, and makes the command exit with 1 so the scheduler can alert; so does '
                .'a row whose publication event fails, which the next run tries again.',
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change, without writing anything')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'How many rows are read at a time', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $batchSize = max(1, (int) $input->getOption('batch-size'));

        $lock = $this->lockFactory->createLock('thelia.content.publish-scheduled', self::LOCK_TTL_SECONDS);

        if (!$lock->acquire()) {
            $output->writeln('<comment>Another run of content:publish-scheduled is in progress, nothing done.</comment>');

            return self::SUCCESS;
        }

        try {
            $report = $this->runner->run($dryRun, $batchSize, static fn () => $lock->refresh());
        } finally {
            $lock->release();
        }

        $output->writeln($dryRun
            ? '<info>Scheduled publication (dry run, nothing is written)</info>'
            : '<info>Scheduled publication</info>');

        foreach ($report->changes() as $change) {
            $output->writeln(\sprintf('  %s%s', $change, $dryRun ? ' (would be)' : ''));
        }

        foreach ($report->warnings() as $warning) {
            $output->writeln(\sprintf('<comment>  %s</comment>', $warning));
        }

        if ([] === $report->changes() && [] === $report->warnings()) {
            $output->writeln('  Nothing is due.');
        }

        return $report->hasWarnings() ? self::FAILURE : self::SUCCESS;
    }
}
