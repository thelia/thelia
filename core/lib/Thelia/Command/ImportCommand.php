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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\File;
use Thelia\Domain\DataTransfer\ImportHandler;
use Thelia\Model\ImportQuery;
use Thelia\Model\LangQuery;

/**
 * Class ImportCommand.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
#[AsCommand(name: 'import', description: 'Import data')]
class ImportCommand extends ContainerAwareCommand
{
    protected function configure(): void
    {
        $this
            ->setHelp('The <info>import</info> command run selected import')
            ->addArgument(
                'ref',
                InputArgument::OPTIONAL,
                'Import reference.',
            )
            ->addArgument(
                'filePath',
                InputArgument::OPTIONAL,
                'File path to import',
            )
            ->addOption(
                'locale',
                null,
                InputOption::VALUE_REQUIRED,
                'Locale for export',
                'en_US',
            )
            ->addOption(
                'list',
                null,
                InputOption::VALUE_NONE,
                'List available imports and exit.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('list')) {
            $this->listImport($output);

            return 0;
        }

        $importRef = $input->getArgument('ref');
        $path = $input->getArgument('filePath');

        if (null === $importRef || null === $path) {
            throw new \RuntimeException('Not enough arguments.'.\PHP_EOL.'If no options are provided, ref and filePath arguments are required.');
        }

        /** @var ImportHandler $importHandler */
        $importHandler = $this->getContainer()->get('thelia.import.handler');

        $import = $importHandler->getImportByRef($importRef);

        if (null === $import) {
            throw new \RuntimeException($importRef." import doesn't exist.");
        }

        $importEvent = $importHandler->import(
            $import,
            new File($input->getArgument('filePath')),
            (new LangQuery())->findOneByLocale($input->getOption('locale')),
        );

        $formattedLine = $this->getHelper('formatter')->formatBlock(
            'Successfully import '.$importEvent->getImport()->getImportedRows().' row(s)',
            'fg=black;bg=green',
            true,
        );
        $output->writeln($formattedLine);

        if (\count($importEvent->getErrors()) > 0) {
            $formattedLine = $this->getHelper('formatter')->formatBlock(
                'With error',
                'fg=black;bg=yellow',
                true,
            );
            $output->writeln($formattedLine);

            foreach ($importEvent->getErrors() as $error) {
                $output->writeln('<comment>'.$error.'</comment>');
            }
        }

        return 0;
    }

    /**
     * Output available imports.
     *
     * @param OutputInterface $output An output interface
     */
    protected function listImport(OutputInterface $output): void
    {
        $table = new Table($output);

        foreach ((new ImportQuery())->find() as $import) {
            $table->addRow([
                $import->getRef(),
                $import->getTitle(),
                $import->getDescription(),
            ]);
        }

        $table
            ->setHeaders([
                'Reference',
                'Title',
                'Description',
            ])
            ->render();
    }
}
