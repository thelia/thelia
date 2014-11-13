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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * generate a new Module
 *
 * Class ModuleGenerateCommand
 * @package Thelia\Command
 * @author Manuel Raynaud <manu@thelia.net>
 */
class ModuleGenerateCommand extends BaseModuleGenerate
{
    protected function configure()
    {
        $this
            ->setName("module:generate")
            ->setDescription("generate all needed files for creating a new Module")
            ->addArgument(
                "name",
                InputArgument::REQUIRED,
                "name wanted for your Module"
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'If defined, it will update the module with missing directories and files (no overrides).'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->module = $this->formatModuleName($input->getArgument("name"));
        $this->moduleDirectory = THELIA_MODULE_DIR . DIRECTORY_SEPARATOR . $this->module;

        try {
            $this->verifyExistingModule();
        } catch (\RuntimeException $ex) {
            if (false === $input->getOption('force')) {
                throw $ex;
            }
        }

        $this->createDirectories();
        $this->createFiles();
        if (method_exists($this, "renderBlock")) {
            // impossible to change output class in CommandTester...
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

        if (!$fs->exists($this->moduleDirectory)) {
            $fs->mkdir($this->moduleDirectory);
        }

        foreach ($this->neededDirectories as $directory) {
            if (!$fs->exists($this->moduleDirectory . DIRECTORY_SEPARATOR . $directory)) {
                $fs->mkdir($this->moduleDirectory . DIRECTORY_SEPARATOR . $directory);
            }
        }
    }

    private function createFiles()
    {
        $fs = new Filesystem();

        try {
            $skeletonDir = str_replace("/", DIRECTORY_SEPARATOR, THELIA_ROOT . "/core/lib/Thelia/Command/Skeleton/Module/");

            // config.xml file
            $filename = $this->moduleDirectory . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "config.xml";
            if (!$fs->exists($filename)) {
                $configContent = file_get_contents($skeletonDir . "config.xml");

                $configContent = str_replace("%%CLASSNAME%%", $this->module, $configContent);
                $configContent = str_replace("%%NAMESPACE%%", $this->module, $configContent);
                $configContent = str_replace("%%NAMESPACE_LOWER%%", strtolower($this->module), $configContent);

                file_put_contents(
                    $filename,
                    $configContent
                );
            }

            // module.xml file
            $filename = $this->moduleDirectory . DIRECTORY_SEPARATOR . "Config". DIRECTORY_SEPARATOR . "module.xml";
            if (!$fs->exists($filename)) {
                $moduleContent = file_get_contents($skeletonDir . "module.xml");

                $moduleContent = str_replace("%%CLASSNAME%%", $this->module, $moduleContent);
                $moduleContent = str_replace("%%NAMESPACE%%", $this->module, $moduleContent);

                file_put_contents($filename, $moduleContent);
            }

            // PHP Class template
            $filename = $this->moduleDirectory . DIRECTORY_SEPARATOR . $this->module . ".php";
            if (!$fs->exists($filename)) {
                $classContent = file_get_contents($skeletonDir . "Class.php.template");

                $classContent = str_replace("%%CLASSNAME%%", $this->module, $classContent);
                $classContent = str_replace("%%NAMESPACE%%", $this->module, $classContent);

                file_put_contents($filename, $classContent);
            }

            // schema.xml file
            $filename = $this->moduleDirectory . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "schema.xml";
            if (!$fs->exists($filename)) {
                $schemaContent = file_get_contents($skeletonDir . "schema.xml");

                $schemaContent = str_replace("%%NAMESPACE%%", $this->module, $schemaContent);

                file_put_contents($filename, $schemaContent);
            }

            // routing.xml file
            $filename = $this->moduleDirectory . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "routing.xml";
            if (!$fs->exists($filename)) {
                $routingContent = file_get_contents($skeletonDir . "routing.xml");

                $routingContent = str_replace("%%NAMESPACE%%", $this->module, $routingContent);
                $routingContent = str_replace("%%CLASSNAME_LOWER%%", strtolower($this->module), $routingContent);

                file_put_contents($filename, $routingContent);
            }

            // I18n sample files
            $filename = $this->moduleDirectory . DIRECTORY_SEPARATOR . "I18n" . DIRECTORY_SEPARATOR . "fr_FR.php";
            if (!$fs->exists($filename)) {
                $fs->copy(
                    $skeletonDir . DIRECTORY_SEPARATOR . "I18n" . DIRECTORY_SEPARATOR . "fr_FR.php",
                    $filename
                );
            }

            $filename = $this->moduleDirectory . DIRECTORY_SEPARATOR . "I18n" . DIRECTORY_SEPARATOR . "en_US.php";
            if (!$fs->exists($filename)) {
                $fs->copy(
                    $skeletonDir . DIRECTORY_SEPARATOR . "I18n" . DIRECTORY_SEPARATOR . "en_US.php",
                    $this->moduleDirectory . DIRECTORY_SEPARATOR . "I18n" . DIRECTORY_SEPARATOR . "en_US.php"
                );
            }
        } catch (\Exception $ex) {
            $fs->remove($this->moduleDirectory);

            throw $ex;
        }
    }
}
