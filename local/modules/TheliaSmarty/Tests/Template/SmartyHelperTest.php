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

namespace TheliaSmarty\Tests\Template;

use TheliaSmarty\Template\SmartyHelper;

/**
 * Class SmartyHelperTest
 * @package Thelia\Tests\Core\Smarty
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class SmartyHelperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SmartyHelper
     */
    protected static $smartyParserHelper;

    public static function setUpBeforeClass()
    {
        self::$smartyParserHelper = new SmartyHelper();
    }

    public function testFunctionsDefinition()
    {
        $content = <<<EOT
Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
quis nostrud {hook name="test"} exercitation ullamco laboris nisi ut aliquip ex ea commodo
consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
EOT;

        $functions = self::$smartyParserHelper->getFunctionsDefinition($content);

        $this->assertCount(1, $functions);
        $this->assertArrayHasKey("name", $functions[0]);
        $this->assertEquals("hook", $functions[0]["name"]);
        $this->assertArrayHasKey("attributes", $functions[0]);
        $this->assertArrayHasKey("name", $functions[0]["attributes"]);
        $this->assertEquals("test", $functions[0]["attributes"]["name"]);
    }

    public function testfunctionsDefinitionVar()
    {
        $content = <<<'EOT'
Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
quis nostrud {hook name=$test} exercitation ullamco laboris nisi ut aliquip ex ea commodo
consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
cillum dolore {function name="{$test}"} eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
EOT;

        $functions = self::$smartyParserHelper->getFunctionsDefinition($content);

        $this->assertCount(2, $functions);

        $this->assertArrayHasKey("name", $functions[0]);
        $this->assertEquals("hook", $functions[0]["name"]);
        $this->assertArrayHasKey("attributes", $functions[0]);
        $this->assertArrayHasKey("name", $functions[0]["attributes"]);
        $this->assertEquals("\$test", $functions[0]["attributes"]["name"]);

        $this->assertArrayHasKey("name", $functions[1]);
        $this->assertEquals("function", $functions[1]["name"]);
        $this->assertArrayHasKey("attributes", $functions[1]);
        $this->assertArrayHasKey("name", $functions[1]["attributes"]);
        $this->assertEquals("{\$test}", $functions[1]["attributes"]["name"]);
    }

    public function testfunctionsDefinitionInnerFunction()
    {
        $content = <<<'EOT'
Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
quis nostrud {hook name={intl l="test"}} exercitation ullamco laboris nisi ut aliquip ex ea commodo
consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
cillum dolore {hook name={intl l="test"}} eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
EOT;

        $functions = self::$smartyParserHelper->getFunctionsDefinition($content);

        $this->assertCount(2, $functions);

        for ($i = 0; $i <= 1; $i++) {
            $this->assertArrayHasKey("name", $functions[$i]);
            $this->assertEquals("hook", $functions[$i]["name"]);
            $this->assertArrayHasKey("attributes", $functions[$i]);
            $this->assertArrayHasKey("name", $functions[$i]["attributes"]);
            $this->assertEquals("{intl l=\"test\"}", $functions[$i]["attributes"]["name"]);
        }
    }

    public function testfunctionsDefinitionSpecificFunction()
    {
        $content = <<<'EOT'
Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
quis nostrud {hook   name="hello world"  } exercitation ullamco laboris nisi ut aliquip ex ea commodo
consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
cillum dolore {function name={intl l="test"}} eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
EOT;

        $functions = self::$smartyParserHelper->getFunctionsDefinition($content, array("hook"));

        $this->assertCount(1, $functions);

        $this->assertArrayHasKey("name", $functions[0]);
        $this->assertEquals("hook", $functions[0]["name"]);
        $this->assertArrayHasKey("attributes", $functions[0]);
        $this->assertArrayHasKey("name", $functions[0]["attributes"]);
        $this->assertEquals("hello world", $functions[0]["attributes"]["name"]);
    }
}
