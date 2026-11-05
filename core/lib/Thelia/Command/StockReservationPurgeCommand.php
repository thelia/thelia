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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Domain\Catalog\Stock\StockReservationPurger;

/**
 * Clears the stock reservations of the cart lines that have run out (see StockReservationPurger).
 * Run by the schedule of the core; what a cart may buy never waits for it.
 */
#[AsCommand(name: 'stock:reservation:purge', description: 'Clear the stock reservations of cart lines that have expired.')]
final class StockReservationPurgeCommand extends Command
{
    public function __construct(private readonly StockReservationPurger $purger)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Lines cleared per statement', (string) StockReservationPurger::DEFAULT_BATCH_SIZE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batchSize = $input->getOption('batch-size');

        if (!\is_string($batchSize) || !ctype_digit($batchSize) || (int) $batchSize < 1) {
            $output->writeln('<error>The batch size must be a whole number above zero.</error>');

            return Command::INVALID;
        }

        $cleared = $this->purger->purgeExpired((int) $batchSize);

        $output->writeln(\sprintf('<info>%d expired stock reservation(s) cleared</info>', $cleared));

        return Command::SUCCESS;
    }
}
