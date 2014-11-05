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
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Application;
use Thelia\Command\ModuleGenerateCommand;

/**
 * test the module:generate command
 *
 * Class ModuleGenerateCommandTest
 * @package Thelia\Tests\Command
 * @author Manuel Raynaud <manu@thelia.net>
 */
class ModuleGenerateCommandTest extends BaseCommandTest
{
    protected $command;
    protected $commandTester;

    public static function clearTest()
    {
        $fs = new Filesystem();

        if ($fs->exists(THELIA_MODULE_DIR . "Test")) {
            $fs->remove(THELIA_MODULE_DIR . "Test");
        }
    }

    public static function setUpBeforeClass()
    {
        self::clearTest();
    }

    public static function tearDownAfterClass()
    {
        self::clearTest();
    }

    public function setUp()
    {
        $application = new Application($this->getKernel());

        $moduleGenerator = new ModuleGenerateCommand();

        $application->add($moduleGenerator);

        $this->command = $application->find("module:generate");
        $this->commandTester = new CommandTester($this->command);
    }

    public function testGenerateModule()
    {
        $tester = $this->commandTester;

        $tester->execute(array(
           "command" => $this->command->getName(),
            "name" => "test"
        ));

        $fs = new Filesystem();

        $this->assertTrue($fs->exists(THELIA_MODULE_DIR . "Test"));
    }

    /**
     * @depends testGenerateModule
     * @expectedException \RuntimeException
     */
    public function testGenerateDuplicateModule()
    {
        $tester = $this->commandTester;

        $tester->execute(array(
            "command" => $this->command->getName(),
            "name" => "test"
        ));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGenerateWithReservedKeyWord()
    {
        $tester = $this->commandTester;

        $tester->execute(array(
           "command" => $this->command->getName(),
            "name" => "thelia"
        ));
    }
}
