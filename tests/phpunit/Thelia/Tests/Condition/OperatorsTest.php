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

namespace Thelia\Condition;

use Thelia\Core\Translation\Translator;

/**
 * Unit Test Operators Class
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class OperatorsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
    }

    public function testOperatorI18n()
    {
        /** @var Translator $stubTranslator */
        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();

        $stubTranslator->expects($this->any())
            ->method('trans')
            ->will($this->returnCallback((array($this, 'callbackI18n'))));

        $actual = Operators::getI18n($stubTranslator, Operators::INFERIOR);
        $expected = 'Less than';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::INFERIOR_OR_EQUAL);
        $expected = 'Less than or equals';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::EQUAL);
        $expected = 'Equal to';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::SUPERIOR_OR_EQUAL);
        $expected = 'Greater than or equals';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::SUPERIOR);
        $expected = 'Greater than';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::DIFFERENT);
        $expected = 'Not equal to';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::IN);
        $expected = 'In';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::OUT);
        $expected = 'Not in';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, 'unexpected operator');
        $expected = 'unexpected operator';
        $this->assertEquals($expected, $actual);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    public function callbackI18n()
    {
        $args = func_get_args();

        return $args[0];
    }
}
