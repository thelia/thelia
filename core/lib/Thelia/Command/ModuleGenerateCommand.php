<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * generate a new Module
 *
 * Class ModuleGenerateCommand
 * @package Thelia\Command
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ModuleGenerateCommand extends BaseModuleGenerate
{
    protected function configure()
    {
        $this
            ->setName("module:generate")
            ->setDescription("generate all needed files for creating a new Module")
            ->addArgument(
                "name" ,
                InputArgument::REQUIRED,
                "name wanted for your Module"
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->module = $this->formatModuleName($input->getArgument("name"));
        $this->moduleDirectory = THELIA_MODULE_DIR . DIRECTORY_SEPARATOR . $this->module;
        $this->verifyExistingModule();

        $this->createDirectories();
        $this->createFiles();
        if (method_exists($this, "renderBlock")) {
            //impossible to change output class in CommandTester...
            $output->renderBlock(array(
                '',
                sprintf("module %s create with success", $this->module),
                "You can now configure your module and complete module.xml file",
                ''
            ), "bg=green;fg=black");
        }

    }

    private function createDirectories()
    {
        $fs = new Filesystem();

        $fs->mkdir($this->moduleDirectory);

        foreach ($this->neededDirectories as $directory) {
            $fs->mkdir($this->moduleDirectory . DIRECTORY_SEPARATOR . $directory);
        }

    }

    private function createFiles()
    {
        $fs = new Filesystem();

        try {
            $skeletonDir = str_replace("/", DIRECTORY_SEPARATOR, THELIA_ROOT . "/core/lib/Thelia/Command/Skeleton/Module/");

            // config.xml file
            $fs->copy($skeletonDir . "config.xml", $this->moduleDirectory . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "config.xml");

            $moduleContent = file_get_contents($skeletonDir . "module.xml");

            $moduleContent = str_replace("%%CLASSNAME%%", $this->module, $moduleContent);
            $moduleContent = str_replace("%%NAMESPACE%%", $this->module, $moduleContent);

            file_put_contents($this->moduleDirectory . DIRECTORY_SEPARATOR . "Config". DIRECTORY_SEPARATOR . "module.xml", $moduleContent);

            // PHP Class template
            $classContent = file_get_contents($skeletonDir . "Class.php.template");

            $classContent = str_replace("%%CLASSNAME%%", $this->module, $classContent);
            $classContent = str_replace("%%NAMESPACE%%", $this->module, $classContent);

            file_put_contents($this->moduleDirectory . DIRECTORY_SEPARATOR . $this->module.".php", $classContent);

            // schema.xml file
            $schemaContent = file_get_contents($skeletonDir . "schema.xml");

            $schemaContent = str_replace("%%NAMESPACE%%", $this->module, $schemaContent);

            file_put_contents($this->moduleDirectory . DIRECTORY_SEPARATOR . "Config". DIRECTORY_SEPARATOR . "schema.xml", $schemaContent);

            // routing.xml file
            $routingContent = file_get_contents($skeletonDir . "routing.xml");

            $routingContent = str_replace("%%NAMESPACE%%", $this->module, $routingContent);
            $routingContent = str_replace("%%CLASSNAME_LOWER%%", strtolower($this->module), $routingContent);

            file_put_contents($this->moduleDirectory . DIRECTORY_SEPARATOR . "Config". DIRECTORY_SEPARATOR . "routing.xml", $routingContent);

            // I18n sample files
            $fs->copy(
                $skeletonDir . DIRECTORY_SEPARATOR . "I18n" . DIRECTORY_SEPARATOR . "fr_FR.php",
                $this->moduleDirectory . DIRECTORY_SEPARATOR . "I18n" . DIRECTORY_SEPARATOR . "fr_FR.php"
            );

            $fs->copy(
                $skeletonDir . DIRECTORY_SEPARATOR . "I18n" . DIRECTORY_SEPARATOR . "en_US.php",
                $this->moduleDirectory . DIRECTORY_SEPARATOR . "I18n" . DIRECTORY_SEPARATOR . "en_US.php"
            );
        } catch (\Exception $ex) {
            $fs->remove($this->moduleDirectory);

            throw $ex;
        }
    }

}
