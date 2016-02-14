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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Action\Module;
use Thelia\Command\ModuleActivateCommand;
use Thelia\Core\Application;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Tests\ContainerAwareTestCase;

/**
 * Class ModuleActivateCommandTest
 *
 * @package Thelia\Tests\Command
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ModuleActivateCommandTest extends ContainerAwareTestCase
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

    /**
     * Use this method to build the container with the services that you need.
     * @param ContainerBuilder $container
     */
    protected function buildContainer(ContainerBuilder $container)
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new Module($container, $eventDispatcher));

        $container->set("event_dispatcher", $eventDispatcher);

        $container->setParameter('kernel.cache_dir', THELIA_CACHE_DIR . 'dev');
    }
}
