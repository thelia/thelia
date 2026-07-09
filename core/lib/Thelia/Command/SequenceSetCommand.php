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

use Propel\Runtime\Propel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Thelia\Domain\Sequence\GaplessSequenceGenerator;
use Thelia\Model\Map\OrderTableMap;

/**
 * Positions a gapless sequence counter, typically when taking over an
 * externally managed numbering series (e.g. switching invoice numbering from
 * a module to the core: set invoice_ref_<year> to the last emitted number).
 */
#[AsCommand(
    name: 'sequence:set',
    description: 'Set a gapless sequence counter (order_ref, invoice_ref_<year>, ...) to a given value',
)]
final class SequenceSetCommand extends Command
{
    public function __construct(
        private readonly GaplessSequenceGenerator $sequenceGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Sequence name (e.g. order_ref, invoice_ref_2026)')
            ->addArgument('value', InputArgument::REQUIRED, 'Current value: the next allocated number will be value + 1')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = (string) $input->getArgument('name');
        $rawValue = (string) $input->getArgument('value');

        if (!ctype_digit($rawValue)) {
            $io->error(\sprintf('Value must be a non-negative integer, got "%s".', $rawValue));

            return Command::FAILURE;
        }

        $this->sequenceGenerator->set($name, (int) $rawValue, Propel::getConnection(OrderTableMap::DATABASE_NAME));

        $io->success(\sprintf('Sequence "%s" set to %s — next allocated number will be %d.', $name, $rawValue, (int) $rawValue + 1));

        return Command::SUCCESS;
    }
}
