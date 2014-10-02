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
use Symfony\Component\Console\Output\OutputInterface;

use Thelia\Model\ModuleQuery;

/**
 * activates a module
 *
 * Class ModuleActivateCommand
 * @package Thelia\Command
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class ModuleActivateCommand extends BaseModuleGenerate
{
    protected function configure()
    {
        $this
            ->setName("module:activate")
            ->setDescription("Activates a module")
            ->addArgument(
                "module",
                InputArgument::REQUIRED,
                "module to activate"
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $moduleCode = $this->formatModuleName($input->getArgument("module"));

        $module = ModuleQuery::create()->findOneByCode($moduleCode);

        if (null === $module) {
            throw new \RuntimeException(sprintf("module %s not found", $moduleCode));
        }

        try {
            $moduleInstance = $module->createInstance();

            if (method_exists($moduleInstance, 'setContainer')) {
                $moduleInstance->setContainer($this->getContainer());
            }

            $moduleInstance->activate();
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf("Activation fail with Exception : [%d] %s", $e->getCode(), $e->getMessage()));
        }

        //impossible to change output class in CommandTester...
        if (method_exists($output, "renderBlock")) {
            $output->renderBlock(array(
                '',
                sprintf("Activation succeed for module %s", $moduleCode),
                ''
            ), "bg=green;fg=black");
        }
    }
}
