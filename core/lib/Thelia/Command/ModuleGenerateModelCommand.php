<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Command;

use Propel\Generator\Command\ModelBuildCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * generate class model for a specific module
 *
 * Class ModuleGenerateModelCommand
 * @package Thelia\Command
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ModuleGenerateModelCommand extends BaseModuleGenerate
{
    protected function configure()
    {
        $this
            ->setName("module:generate:model")
            ->setDescription("generate model for a specific module")
            ->addArgument(
                "name",
                InputArgument::REQUIRED,
                "module name"
            )
            ->addOption(
                "generate-sql",
                null,
                InputOption::VALUE_NONE,
                "with this option generate sql file at the same time"
            )
        ;

    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->module = $this->formatModuleName($input->getArgument("name"));
        $this->moduleDirectory = THELIA_MODULE_DIR . DS . $this->module;

        $fs = new Filesystem();

        if ($fs->exists($this->moduleDirectory) === false) {
            throw new \RuntimeException(sprintf("%s module does not exists", $this->module));
        }

        if ($fs->exists($this->moduleDirectory . DS . "Config" . DS . "schema.xml") === false) {
            throw new \RuntimeException("schema.xml not found in Config directory. Needed file for generating model");
        }

        $this->generateModel($output);

        $output->renderBlock(array(
           '',
           'Model generated successfuly',
           ''
        ), 'bg=green;fg=black');

        if ($input->getOption("generate-sql")) {
            $output->writeln(' ');
            $this->generateSql($output);
        }
    }

    protected function generateSql(OutputInterface $output)
    {

        $command = $this->getApplication()->find("module:generate:sql");

        $command->run(
            new ArrayInput(array(
                "command" => $command->getName(),
                "name" => $this->module
            )),
            $output
        );
    }

    protected function generateModel(OutputInterface $output)
    {
        $fs = new Filesystem();
        $moduleBuildPropel = new ModelBuildCommand();
        $moduleBuildPropel->setApplication($this->getApplication());

        $moduleBuildPropel->run(
            new ArrayInput(array(
                "command" => $moduleBuildPropel->getName(),
                "--output-dir" => THELIA_MODULE_DIR,
                "--input-dir" => $this->moduleDirectory . DS ."Config"
            )),
            $output
        );

        $verifyDirectories = array(
            THELIA_MODULE_DIR . DS . "Thelia",
            $this->moduleDirectory . DS . "Model" . DS . "Thelia"
        );

        foreach ($verifyDirectories as $directory) {
            if ($fs->exists($directory)) {
                $fs->remove($directory);
            }
        }

    }

}
