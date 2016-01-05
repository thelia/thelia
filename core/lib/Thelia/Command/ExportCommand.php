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
use Thelia\Core\DependencyInjection\Compiler\RegisterArchiverPass;
use Thelia\Core\DependencyInjection\Compiler\RegisterSerializerPass;
use Thelia\Model\ExportQuery;
use Thelia\Model\LangQuery;

/**
 * Class ExportCommand
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ExportCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('export')
            ->setDescription('Export data')
            ->setHelp('The <info>export</info> command run selected export')
            ->addArgument(
                'ref',
                InputArgument::OPTIONAL,
                'Export reference.'
            )
            ->addArgument(
                'serializer',
                InputArgument::OPTIONAL,
                'Serializer identifier.'
            )
            ->addArgument(
                'archiver',
                InputArgument::OPTIONAL,
                'Archiver identifier.'
            )
            ->addOption(
                'locale',
                null,
                InputOption::VALUE_REQUIRED,
                'Locale for export',
                'en_US'
            )
            ->addOption(
                'list-export',
                null,
                InputOption::VALUE_NONE,
                'List available exports and exit.'
            )
            ->addOption(
                'list-serializer',
                null,
                InputOption::VALUE_NONE,
                'List available serializers and exit.'
            )
            ->addOption(
                'list-archiver',
                null,
                InputOption::VALUE_NONE,
                'List available archivers and exit.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('list-export')) {
            $this->listExport($output);

            return;
        }

        if ($input->getOption('list-serializer')) {
            $this->listSerializer($output);

            return;
        }

        if ($input->getOption('list-archiver')) {
            $this->listArchiver($output);

            return;
        }

        $exportRef = $input->getArgument('ref');
        $serializer = $input->getArgument('serializer');
        if ($exportRef === null || $serializer === null) {
            throw new \RuntimeException(
                'Not enough arguments.' . PHP_EOL . 'If no options are provided, ref and serializer arguments are required.'
            );
        }

        /** @var \Thelia\Handler\Exporthandler $exportHandler */
        $exportHandler = $this->getContainer()->get('thelia.export.handler');

        $export = $exportHandler->getExportByRef($exportRef);
        if ($export === null) {
            throw new \RuntimeException(
                $exportRef . ' export doesn\'t exist.'
            );
        }

        $serializerManager = $this->getContainer()->get(RegisterSerializerPass::MANAGER_SERVICE_ID);
        $serializer = $serializerManager->get($serializer);

        $archiver = null;
        if ($input->getArgument('archiver')) {
            /** @var \Thelia\Core\Archiver\ArchiverManager $archiverManager */
            $archiverManager = $this->getContainer()->get(RegisterArchiverPass::MANAGER_SERVICE_ID);
            $archiver = $archiverManager->get($input->getArgument('archiver'));
        }

        $exportEvent = $exportHandler->export(
            $export,
            $serializer,
            $archiver,
            (new LangQuery)->findOneByLocale($input->getOption('locale'))
        );

        $formattedLine = $this->getHelper('formatter')->formatBlock(
            'Export finish',
            'fg=black;bg=green',
            true
        );
        $output->writeln($formattedLine);
        $output->writeln('<info>Export available at path:</info>');
        $output->writeln('<comment>' . $exportEvent->getFilePath() . '</comment>');
    }

    /**
     * Output available exports
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output An output interface
     */
    protected function listExport(OutputInterface $output)
    {
        $table = new TableHelper;

        foreach ((new ExportQuery)->find() as $export) {
            $table->addRow([
                $export->getRef(),
                $export->getTitle(),
                $export->getDescription()
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

    /**
     * Output available serializers
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output An output interface
     */
    protected function listSerializer(OutputInterface $output)
    {
        $table = new TableHelper;

        $serializerManager = $this->getContainer()->get(RegisterSerializerPass::MANAGER_SERVICE_ID);
        foreach ($serializerManager->getSerializers() as $serializer) {
            $table->addRow([
                $serializer->getId(),
                $serializer->getName(),
                $serializer->getExtension(),
                $serializer->getMimeType()
            ]);
        }

        $table
            ->setHeaders([
                'Id',
                'Name',
                'Extension',
                'MIME type'
            ])
            ->render($output)
        ;
    }

    /**
     * Output available archivers
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output An output interface
     */
    protected function listArchiver(OutputInterface $output)
    {
        $table = new TableHelper;

        $archiverManager = $this->getContainer()->get(RegisterArchiverPass::MANAGER_SERVICE_ID);
        foreach ($archiverManager->getArchivers(true) as $archiver) {
            $table->addRow([
                $archiver->getId(),
                $archiver->getName(),
                $archiver->getExtension(),
                $archiver->getMimeType()
            ]);
        }

        $table
            ->setHeaders([
                'Id',
                'Name',
                'Extension',
                'MIME type'
            ])
            ->render($output)
        ;
    }
}
