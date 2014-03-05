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
namespace Thelia\Tests\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Command\ModuleDeactivateCommand;
use Thelia\Core\Application;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Class ModuleDeactivateCommandTest
 *
 * @package Thelia\Tests\Command
 * @author Nicolas Villa <nicolas@libre-shop.com>
 */
class ModuleDeactivateCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testModuleDeactivateCommand()
    {
        $module = ModuleQuery::create()->findOne();

        if (null !== $module) {

            $prev_activation_status = $module->getDeactivate();

            $application = new Application($this->getKernel());

            $module->setDeactivate(BaseModule::IS_NOT_ACTIVATED);
            $module->save();

            $moduleDeactivate = new ModuleDeactivateCommand();
            $moduleDeactivate->setContainer($this->getContainer());

            $application->add($moduleDeactivate);

            $command = $application->find("module:deactivate");
            $commandTester = new CommandTester($command);
            $commandTester->execute(array(
                "command" => $command->getName(),
                "module" => $module->getCode(),
            ));

            $deactivated = ModuleQuery::create()->findPk($module->getId())->getDeactivate();

            // Restore activation status
            $module->setDeactivate($prev_activation_status)->save();

            $this->assertEquals(BaseModule::IS_ACTIVATED, $deactivated);
        }
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage module Letshopethismoduledoesnotexists not found
     */
    public function testModuleDeactivateCommandUnknownModule()
    {
        $testedModule = ModuleQuery::create()->findOneByCode('Letshopethismoduledoesnotexists');

        if (null == $testedModule) {
            $application = new Application($this->getKernel());

            $moduleDeactivate = new ModuleDeactivateCommand();
            $moduleDeactivate->setContainer($this->getContainer());

            $application->add($moduleDeactivate);

            $command = $application->find("module:deactivate");
            $commandTester = new CommandTester($command);
            $commandTester->execute(array(
                "command" => $command->getName(),
                "module" => "letshopethismoduledoesnotexists",
            ));

            $out = true;
        }
    }

    public function getKernel()
    {
        $kernel = $this->getMock("Symfony\Component\HttpKernel\KernelInterface");

        return $kernel;
    }

    public function getContainer()
    {
        $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

        return $container;
    }
}
