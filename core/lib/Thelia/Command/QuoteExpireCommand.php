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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Thelia\Domain\Quote\QuoteFacade;

/**
 * Writes the expiry of the quotes whose last day of validity is past.
 *
 * A quote is read as expired the day after its last valid day whether this command ran or not:
 * running it only makes the list of the shop and the filters on the state agree with what is read.
 * It can run as often as the scheduler likes, a second run finds nothing left to do.
 */
#[AsCommand(
    name: 'quote:expire',
    description: 'Mark as expired the quotes whose validity ended before today',
)]
final class QuoteExpireCommand extends Command
{
    public function __construct(
        private readonly QuoteFacade $quoteFacade,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $expired = $this->quoteFacade->expireOverdue($this->clock->now()->setTime(0, 0));

        $io->success(0 === $expired ? 'No quote to expire.' : \sprintf('%d quote(s) expired.', $expired));

        return Command::SUCCESS;
    }
}
