<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Command;


use Propel\Generator\Command\ModelBuildCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Filesystem\Filesystem;

class ModuleGenerateModelCommand extends BaseModuleGenerate {

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
            throw new \RuntimeException(sprintf("%s module does not exists"));
        }

        if ($fs->exists($this->moduleDirectory . DS . "Config" . DS . "schema.xml") === false) {
            throw new \RuntimeException("schema.xml not found in Config directory. Needed file for generating model");
        }

        $this->generateModel();

        if ($input->getOption("generate-sql")) {
            $this->generateSql();
        }
    }

    protected function generateSql()
    {
        $sqlBuild = new ModuleGenerateSqlCommand();
        $sqlBuild->setApplication($this->getApplication());

        $sqlBuild->run(
            new ArrayInput(array(
                "command" => $sqlBuild->getName(),
                "name" => $this->module
            )),
            new StreamOutput(fopen('php://memory', 'w', false))
        );
    }

    protected function generateModel()
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
            new StreamOutput(fopen('php://memory', 'w', false))
        );

        if ($fs->exists(THELIA_MODULE_DIR . DS . "Thelia")) {
            $fs->remove(THELIA_MODULE_DIR . DS . "Thelia");
        }
    }

}