<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Command;

use Symfony\Component\Console\Helper\TableHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\File;
use Thelia\Model\ImportQuery;
use Thelia\Model\LangQuery;

/**
 * Class ImportCommand
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ImportCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('import')
            ->setDescription('Import data')
            ->setHelp('The <info>import</info> command run selected import')
            ->addArgument(
                'ref',
                InputArgument::OPTIONAL,
                'Import reference.'
            )
            ->addArgument(
                'filePath',
                InputArgument::OPTIONAL,
                'File path to import'
            )
            ->addOption(
                'locale',
                null,
                InputOption::VALUE_REQUIRED,
                'Locale for export',
                'en_US'
            )
            ->addOption(
                'list',
                null,
                InputOption::VALUE_NONE,
                'List available imports and exit.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('list')) {
            $this->listImport($output);

            return;
        }

        $importRef = $input->getArgument('ref');
        $path = $input->getArgument('filePath');
        if ($importRef === null || $path === null) {
            throw new \RuntimeException(
                'Not enough arguments.' . PHP_EOL . 'If no options are provided, ref and filePath arguments are required.'
            );
        }

        /** @var \Thelia\Handler\ImportHandler $importHandler */
        $importHandler = $this->getContainer()->get('thelia.import.handler');

        $import = $importHandler->getImportByRef($importRef);
        if ($import === null) {
            throw new \RuntimeException(
                $importRef . ' import doesn\'t exist.'
            );
        }

        $importEvent = $importHandler->import(
            $import,
            new File($input->getArgument('filePath')),
            (new LangQuery)->findOneByLocale($input->getOption('locale'))
        );

        $formattedLine = $this->getHelper('formatter')->formatBlock(
            'Successfully import ' . $importEvent->getImport()->getImportedRows() . ' row(s)',
            'fg=black;bg=green',
            true
        );
        $output->writeln($formattedLine);

        if (count($importEvent->getErrors()) > 0) {
            $formattedLine = $this->getHelper('formatter')->formatBlock(
                'With error',
                'fg=black;bg=yellow',
                true
            );
            $output->writeln($formattedLine);

            foreach ($importEvent->getErrors() as $error) {
                $output->writeln('<comment>' . $error . '</comment>');
            }
        }
    }

    /**
     * Output available imports
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output An output interface
     */
    protected function listImport(OutputInterface $output)
    {
        $table = new TableHelper;

        foreach ((new ImportQuery)->find() as $import) {
            $table->addRow([
                $import->getRef(),
                $import->getTitle(),
                $import->getDescription()
            ]);
        }

        $table
            ->setHeaders([
                'Reference',
                'Title',
                'Description'
            ])
            ->render($output)
        ;
    }
}
