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

use PHPUnit_Framework_TestCase;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Thelia\Command\ModuleRefreshCommand;
use Thelia\Core\Application;
use Thelia\Model\ModuleQuery;
use Thelia\Module\ModuleManagement;

/**
 * Class ModuleRefreshCommandTest
 * Test refresh modules list command
 *
 * @package Thelia\Tests\Command
 * @author  Jérôme Billiras <jbilliras@openstudio.fr>
 *
 * Date: 2014-06-06
 * Time: 17:29
 */
class ModuleRefreshCommandTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test ModuleRefreshCommand
     */
    public function testModuleRefreshCommand()
    {
        $moduleManagement = new ModuleManagement;
        $moduleManagement->updateModules($this->getContainer());

        $module = ModuleQuery::create()->filterByType(1)->orderByPosition(Criteria::DESC)->findOne();

        if ($module !== null) {
            $module->delete();

            $application = new Application($this->getKernel());

            $moduleRefresh = new ModuleRefreshCommand;
            $moduleRefresh->setContainer($this->getContainer());

            $application->add($moduleRefresh);

            $command = $application->find('module:refresh');
            $commandTester = new CommandTester($command);
            $commandTester->execute([
                'command' => $command->getName()
            ]);

            $expected = $module;
            $actual = ModuleQuery::create()->filterByType(1)->orderByPosition(Criteria::DESC)->findOne();

            $this->assertEquals($expected->getCode(), $actual->getCode(), 'Last standard module code must be same after deleting this one and calling module:refresh');
            $this->assertEquals($expected->getType(), $actual->getType(), 'Last standard module type must be same after deleting this one and calling module:refresh');
            $this->assertEquals($expected->getFullNamespace(), $actual->getFullNamespace(), 'Last standard module namespace must be same after deleting this one and calling module:refresh');

            // Restore activation status
            $actual
                ->setActivate($expected->getActivate())
                ->save();
        } else {
            $this->markTestIncomplete(
                'This test cannot be complete without at least one standard module.'
            );
        }
    }

    /**
     * Get HttpKernel mock
     *
     * @return Kernel Not really a Kernel but the mocked one
     */
    public function getKernel()
    {
        $kernel = $this->getMock('Symfony\\Component\\HttpKernel\\KernelInterface');

        return $kernel;
    }

    /**
     * Get new ContainerBuilder
     *
     * @return ContainerBuilder
     */
    public function getContainer()
    {
        $container = new ContainerBuilder;

        return $container;
    }
}
