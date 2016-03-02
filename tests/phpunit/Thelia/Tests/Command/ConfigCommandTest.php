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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Command\ConfigCommand;
use Thelia\Core\Application;
use Thelia\Model\Config;
use Thelia\Model\ConfigQuery;

/**
 * Class ConfigCommandTest
 * @package Command
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ConfigCommandTest extends BaseCommandTest
{
    /** @var ConfigCommand */
    protected $command;

    /** @var  CommandTester */
    protected $commandTester;

    const PREFIX_NAME = "config_command_test_";

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
        if (null === $this->commandTester) {
            $application   = new Application($this->getKernel());
            $configCommand = new ConfigCommand();

            $application->add($configCommand);

            $this->command       = $application->find("thelia:config");
            $this->commandTester = new CommandTester($this->command);
        }
    }

    public function testArguments()
    {
        $tester = $this->commandTester;

        $commands = $this->getFakeCommands();

        foreach ($commands as $command) {
            $arguments = array_merge(
                $command['args'],
                ["command" => $this->command->getName()]
            );

            $tester->execute($arguments);

            $this->assertStringContains(
                $tester->getDisplay(),
                $command["out"],
                "Should display : " . $command["out"]
            );
        }
    }

    public function testList()
    {
        $tester = $this->commandTester;

        $tester->execute([
            "command" => $this->command->getName(),
            "COMMAND" => "list"
        ]);

        $out = $tester->getDisplay();

        $vars = ConfigQuery::create()->find();

        /** @var Config $var */
        foreach ($vars as $var) {
            $this->assertStringContains(
                $out,
                $var->getName(),
                "Should display : " . $var->getName()
            );
        }
    }

    public function testGetSetDelete()
    {
        $tester = $this->commandTester;

        $varName = $this->getRandomVariableName();

        // Get
        $tester->execute([
            "command" => $this->command->getName(),
            "COMMAND" => "get",
            "name"    => $varName
        ]);

        $expected = sprintf("Unknown variable '%s'", $varName);

        $this->assertStringContains(
            $tester->getDisplay(),
            $expected,
            "Should display : " . $expected
        );

        // Set
        $tester->execute([
            "command" => $this->command->getName(),
            "COMMAND" => "set",
            "name"    => $varName,
            "value"   => "0"
        ]);

        $this->assertVariableEqual($varName, "0");

        $tester->execute([
            "command" => $this->command->getName(),
            "COMMAND" => "set",
            "name"    => $varName,
            "value"   => "Thelia"
        ]);

        $this->assertVariableEqual($varName, "Thelia");

        $tester->execute([
            "command"   => $this->command->getName(),
            "COMMAND"   => "set",
            "name"      => $varName,
            "value"     => "Thelia",
            "--secured" => true,
            "--visible" => true,
        ]);

        $this->assertVariableEqual($varName, "Thelia", 1, 0);

        $tester->execute([
            "command"   => $this->command->getName(),
            "COMMAND"   => "set",
            "name"      => $varName,
            "value"     => "THELIA",
            "--visible" => true
        ]);

        $this->assertVariableEqual($varName, "THELIA", 0, 0);

        // DELETE
        $tester->execute([
            "command" => $this->command->getName(),
            "COMMAND" => "delete",
            "name"    => $varName
        ]);

        $this->assertNull(
            ConfigQuery::read($varName),
            sprintf("Variable '%s' should not exist", $varName)
        );
    }

    public static function clearTest()
    {
        ConfigQuery::create()
            ->filterByName(self::PREFIX_NAME . '%', Criteria::LIKE)
            ->delete();
    }

    protected function getRandomVariableName()
    {
        return sprintf(
            "%s%s",
            self::PREFIX_NAME,
            substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 10)
        );
    }

    protected function assertVariableEqual($name, $value, $secured = 0, $hidden = 1)
    {
        $var = ConfigQuery::create()->findOneByName($name);

        $this->assertNotNull($var, sprintf("Variable '%s' should exist", $name));

        $this->assertEquals(
            $var->getName(),
            $name,
            sprintf("Variable '%s' should have name '%s' :/", $var->getName(), $name)
        );

        $this->assertEquals(
            $var->getValue(),
            $value,
            sprintf(
                "Variable '%s' should have value '%s' ('%s' found)",
                $name,
                $value,
                $var->getValue()
            )
        );

        $this->assertEquals(
            $var->getSecured(),
            $secured,
            sprintf("Variable '%s' should be %s secured", $name, $secured === 1 ? '' : 'NOT')
        );

        $this->assertEquals(
            $var->getHidden(),
            $hidden,
            sprintf("Variable '%s' should be %s hidden", $name, $hidden === 1 ? '' : 'NOT')
        );
    }

    protected function assertStringContains($data, $needle, $message = "")
    {
        $this->assertTrue((false !== strpos($data, $needle)), $message);
    }

    protected function assertStringNotContains($data, $needle, $message = "")
    {
        $this->assertTrue((false === strpos($data, $needle)), $message);
    }

    protected function getFakeCommands()
    {
        $commands = [
            [
                "args" => [
                    'COMMAND' => 'hello',
                ],
                'out'  => "Unknown argument 'COMMAND'"
            ],
            [
                "args" => [
                    'COMMAND' => 'get',
                ],
                'out'  => "Need argument 'name'"
            ],
            [
                "args" => [
                    'COMMAND' => 'get',
                    'name'    => 'unknown_var_name',
                ],
                'out'  => "Unknown variable 'unknown_var_name'"
            ],
            [
                "args" => [
                    'COMMAND' => 'delete',
                    'name'    => 'unknown_var_name',
                ],
                'out'  => "Unknown variable 'unknown_var_name'"
            ],
            [
                "args" => [
                    'COMMAND' => 'set',
                    'name'    => 'unknown_var_name',
                ],
                'out'  => "Need argument 'name' and 'value'"
            ]
        ];

        return $commands;
    }
}
