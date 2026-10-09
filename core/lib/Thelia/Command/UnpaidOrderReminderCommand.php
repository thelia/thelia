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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Thelia\Domain\Order\Reminder\UnpaidOrderReminderRunner;
use Thelia\Domain\Order\Reminder\UnpaidOrderReminderSettings;

/**
 * Reminds the unpaid orders, and cancels them, as the merchant's schedule says. Meant to
 * be scheduled by the host, every fifteen minutes or every hour: a run acts on a bounded
 * number of orders, a step is never done twice, and a run that finds another one going
 * leaves it alone.
 */
#[AsCommand(name: 'order:remind-unpaid', description: 'Send the payment reminders of the unpaid orders and cancel them, as the reminder schedule of the shop says.')]
class UnpaidOrderReminderCommand extends ContainerAwareCommand
{
    public const LOCK_NAME = 'thelia.unpaid_order_reminder';

    private const DEFAULT_LIMIT = 200;

    /**
     * Longer than a run of the default size takes; released at the end of the run anyway.
     */
    private const LOCK_TTL_SECONDS = 1800;

    public function __construct(
        private readonly UnpaidOrderReminderRunner $runner,
        private readonly UnpaidOrderReminderSettings $settings,
        private readonly LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what the run would do, without sending or changing anything')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'The most orders a run acts on; the next run goes on', (string) self::DEFAULT_LIMIT);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (string) $input->getOption('limit');

        if (!ctype_digit($limit) || (int) $limit < 1) {
            $output->writeln('<error>--limit is a whole number of orders, at least 1.</error>');

            return self::INVALID;
        }

        if ($this->settings->schedule()->isEmpty()) {
            $output->writeln('<info>No reminder schedule is set: nothing to do.</info>');

            return self::SUCCESS;
        }

        $lock = $this->lockFactory->createLock(self::LOCK_NAME, self::LOCK_TTL_SECONDS);

        if (!$lock->acquire()) {
            $output->writeln('<comment>Another run is in progress: this one stops.</comment>');

            return self::SUCCESS;
        }

        try {
            $dryRun = (bool) $input->getOption('dry-run');
            $report = $this->runner->run(new \DateTimeImmutable(), (int) $limit, $dryRun);
        } finally {
            $lock->release();
        }

        if ([] === $report->outcomes()) {
            $output->writeln('<info>No unpaid order is due for a step.</info>');

            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Order', 'Step', 'Action', 'Result', 'Error']);

        foreach ($report->outcomes() as $outcome) {
            $table->addRow([$outcome->orderRef, $outcome->delayInHours.' h', $outcome->action, $outcome->status, $outcome->error ?? '']);
        }

        $table->render();

        if ($dryRun) {
            $output->writeln('<info>Dry run: nothing was sent nor changed.</info>');
        }

        return $report->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}
