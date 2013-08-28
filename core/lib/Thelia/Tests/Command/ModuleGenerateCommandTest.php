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
namespace Thelia\Tests\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Application;
use Thelia\Command\ModuleGenerateCommand;

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
