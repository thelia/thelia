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
use Thelia\Domain\DataTransfer\Service\HandlerCleaner;

#[AsCommand(name: 'import-export:clean', description: 'Delete the exports and imports whose handler class is no longer available.')]
class DataTransferCleanCommand extends ContainerAwareCommand
{
    public function __construct(protected HandlerCleaner $handlerCleaner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'Exports and imports are declared by modules, and the entries they leave behind when they are '
                ."removed keep pointing at a class that no longer exists.\n"
                .'This deletes those entries, and the export or import categories they leave empty. '
                .'Use --dry-run to list them first.',
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the entries that would be deleted, and delete nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$exports, $imports] = $this->handlerCleaner->findUnavailableHandlers();

        if ([] === $exports && [] === $imports) {
            $output->writeln('<info>No export or import points at a missing handler class.</info>');

            return 0;
        }

        foreach ($exports as $export) {
            $output->writeln(\sprintf('export <comment>%s</comment> => %s', $export->getRef(), $export->getHandleClass()));
        }

        foreach ($imports as $import) {
            $output->writeln(\sprintf('import <comment>%s</comment> => %s', $import->getRef(), $import->getHandleClass()));
        }

        if ($input->getOption('dry-run')) {
            $output->writeln(\sprintf('<info>%d entrie(s) would be deleted.</info>', \count($exports) + \count($imports)));

            return 0;
        }

        $removed = $this->handlerCleaner->removeUnavailableHandlers();

        $output->writeln(\sprintf('<info>%d entrie(s) deleted.</info>', $removed));

        return 0;
    }
}
