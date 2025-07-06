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
use RuntimeException;
use Propel\Generator\Command\ModelBuildCommand;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\PropelInitService;

/**
 * generate class model for a specific module.
 *
 * Class ModuleGenerateModelCommand
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
#[AsCommand(name: 'module:generate:model', description: 'generate model for a specific module')]
class ModuleGenerateModelCommand extends BaseModuleGenerate
{
    protected function configure(): void
    {
        $this
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'module name'
            )
            ->addOption(
                'generate-sql',
                null,
                InputOption::VALUE_NONE,
                'with this option generate sql file at the same time'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->module = $this->formatModuleName($input->getArgument('name'));
        $this->moduleDirectory = THELIA_LOCAL_MODULE_DIR.$this->module;

        $fs = new Filesystem();

        if ($fs->exists($this->moduleDirectory) === false) {
            throw new RuntimeException(sprintf('%s module does not exists', $this->module));
        }

        if ($fs->exists($this->moduleDirectory.DS.'Config'.DS.'schema.xml') === false) {
            throw new RuntimeException('schema.xml not found in Config directory. Needed file for generating model');
        }

        $this->checkModuleSchema();

        $this->generateModel($output);

        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');
        $formattedBlock = $formatter->formatBlock(
            'Model generated successfully',
            'bg=green;fg=black'
        );
        $output->writeln($formattedBlock);

        if ($input->getOption('generate-sql')) {
            $output->writeln(' ');
            $this->generateSql($output);
        }

        return 0;
    }

    protected function generateSql(OutputInterface $output): void
    {
        $command = $this->getApplication()->find('module:generate:sql');

        $command->run(
            new ArrayInput([
                'command' => $command->getName(),
                'name' => $this->module,
            ]),
            $output
        );
    }

    protected function generateModel(OutputInterface $output): void
    {
        $schemaDir = $this->generateGlobalSchemaForModule();

        /** @var PropelInitService $propelInitService */
        $propelInitService = $this->getContainer()->get('thelia.propel.init');

        $propelInitService->runCommand(
            new ModelBuildCommand(),
            [
                '--config-dir' => $propelInitService->getPropelConfigDir(),
                '--schema-dir' => $schemaDir,
            ],
            $output
        );
    }
}
