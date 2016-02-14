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

use Propel\Generator\Command\SqlBuildCommand;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * generate sql for a specific module
 *
 * Class ModuleGenerateSqlCommand
 * @package Thelia\Command
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ModuleGenerateSqlCommand extends BaseModuleGenerate
{
    public function configure()
    {
        $this
            ->setName("module:generate:sql")
            ->setDescription("Generate the sql from schema.xml file")
            ->addArgument(
                "name",
                InputArgument::REQUIRED,
                "Module name"
            )
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->module = $this->formatModuleName($input->getArgument("name"));
        $this->moduleDirectory = THELIA_MODULE_DIR . $this->module;

        $fs = new Filesystem();

        if ($fs->exists($this->moduleDirectory) === false) {
            throw new \RuntimeException(sprintf("%s module does not exists", $this->module));
        }

        if ($fs->exists($this->moduleDirectory . DS . "Config" . DS . "schema.xml") === false) {
            throw new \RuntimeException("schema.xml not found in Config directory. Needed file for generating model");
        }

        $sqlBuild = new SqlBuildCommand();
        $sqlBuild->setApplication($this->getApplication());

        $sqlBuild->run(
            new ArrayInput(array(
                "command" => $sqlBuild->getName(),
                "--output-dir" => $this->moduleDirectory . DS ."Config",
                "--input-dir" => $this->moduleDirectory . DS ."Config"
            )),
            $output
        );

        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');
        $formattedBlock = $formatter->formatBlock(
            [
                'Sql generated successfully',
                'File available in your module config directory',
            ],
            'bg=green;fg=black'
        );
        $output->writeln($formattedBlock);
    }
}
