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
use Thelia\Command\ModuleActivateCommand;
use Thelia\Core\Application;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Class ModuleActivateCommandTest
 *
 * @package Thelia\Tests\Command
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ModuleActivateCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testModuleActivateCommand()
    {
        $module = ModuleQuery::create()->findOne();

        if (null !== $module) {

            $prev_activation_status = $module->getActivate();

            $application = new Application($this->getKernel());

            $module->setActivate(BaseModule::IS_NOT_ACTIVATED);
            $module->save();

            $moduleActivate = new ModuleActivateCommand();
            $moduleActivate->setContainer($this->getContainer());

            $application->add($moduleActivate);

            $command = $application->find("module:activate");
            $commandTester = new CommandTester($command);
            $commandTester->execute(array(
                "command" => $command->getName(),
                "module" => $module->getCode(),
            ));

            $activated = ModuleQuery::create()->findPk($module->getId())->getActivate();

            // Restore activation status
            $module->setActivate($prev_activation_status)->save();

            $this->assertEquals(BaseModule::IS_ACTIVATED, $activated);
        }
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage module Letshopethismoduledoesnotexists not found
     */
    public function testModuleActivateCommandUnknownModule()
    {
        $testedModule = ModuleQuery::create()->findOneByCode('Letshopethismoduledoesnotexists');

        if (null == $testedModule) {
            $application = new Application($this->getKernel());

            $moduleActivate = new ModuleActivateCommand();
            $moduleActivate->setContainer($this->getContainer());

            $application->add($moduleActivate);

            $command = $application->find("module:activate");
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
