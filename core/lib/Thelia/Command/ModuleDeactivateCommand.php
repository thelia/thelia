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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Thelia\Model\ModuleQuery;

/**
 * DeActivates a module
 *
 * Class ModuleDeactivateCommand
 * @package Thelia\Command
 * @author Nicolas Villa <nicolas@libre-shop.com>
 *
 */
class ModuleDeactivateCommand extends BaseModuleGenerate
{
    protected function configure()
    {
        $this
            ->setName("module:deactivate")
            ->setDescription("Deactivate a module")
            ->addArgument(
                "module" ,
                InputArgument::REQUIRED,
                "module to deactivate"
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
            $moduleReflection = new \ReflectionClass($module->getFullNamespace());

            $moduleInstance = $moduleReflection->newInstance();

            $moduleInstance->deActivate();
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf("Deactivation fail with Exception : [%d] %s", $e->getCode(), $e->getMessage()));
        }

        //impossible to change output class in CommandTester...
        if (method_exists($output, "renderBlock")) {
            $output->renderBlock(array(
                '',
                sprintf("Deactivation succeed for module %s", $moduleCode),
                ''
            ), "bg=green;fg=black");
        }
    }
}
