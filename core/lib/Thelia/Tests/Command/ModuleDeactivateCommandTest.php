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
            $prev_activation_status = $module->getActivate();

            $application = new Application($this->getKernel());

            $module->setActivate(BaseModule::IS_ACTIVATED);
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

            $deactivated = ModuleQuery::create()->findPk($module->getId())->getActivate();

            // Restore activation status
            $module->setActivate($prev_activation_status)->save();

            $this->assertEquals(BaseModule::IS_NOT_ACTIVATED, $deactivated);
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
