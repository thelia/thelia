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

use Propel\Generator\Command\SqlBuildCommand;
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
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
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
        $this->moduleDirectory = THELIA_MODULE_DIR . DS . $this->module;

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

        $output->renderBlock(array(
            '',
            'Sql generated successfuly',
            'File available in your module config directory',
            ''
        ), 'bg=green;fg=black');

    }
}
