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

use Propel\Generator\Command\SqlBuildCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\PropelInitService;

/**
 * generate sql for a specific module.
 *
 * Class ModuleGenerateSqlCommand
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
#[AsCommand(name: 'module:generate:sql', description: 'Generate the sql from schema.xml file')]
class ModuleGenerateSqlCommand extends BaseModuleGenerate
{
    protected function configure(): void
    {
        $this
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Module name',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->module = $this->formatModuleName($input->getArgument('name'));
        $this->moduleDirectory = THELIA_LOCAL_MODULE_DIR.$this->module;

        $fs = new Filesystem();

        if (false === $fs->exists($this->moduleDirectory)) {
            throw new \RuntimeException(\sprintf('%s module does not exists', $this->module));
        }

        if (false === $fs->exists($this->moduleDirectory.DS.'Config'.DS.'schema.xml')) {
            throw new \RuntimeException('schema.xml not found in Config directory. Needed file for generating model');
        }

        $this->checkModuleSchema();

        /** @var PropelInitService $propelInitService */
        $propelInitService = $this->getContainer()->get('thelia.propel.init');

        $propelInitService->runCommand(
            new SqlBuildCommand(),
            [
                '--output-dir' => $this->moduleDirectory.'/Config',
                '--schema-dir' => $this->moduleDirectory.'/Config',
                '--config-dir' => $propelInitService->getPropelConfigDir(),
            ],
            $output,
        );

        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');
        $formattedBlock = $formatter->formatBlock(
            [
                'Sql generated successfully',
                'File available in your module config directory',
            ],
            'bg=green;fg=black',
        );
        $output->writeln($formattedBlock);

        return 0;
    }
}
