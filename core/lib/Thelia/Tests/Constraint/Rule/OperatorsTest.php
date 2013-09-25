<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace Thelia\Coupon;

use Thelia\Constraint\Rule\Operators;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test Operators Class
 *
 * @package Constraint
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

//    public function testSomething()
//    {
//        // Stop here and mark this test as incomplete.
//        $this->markTestIncomplete(
//            'This test has not been implemented yet.'
//        );
//    }

    public function testOperatorI18n()
    {
        $stubTranslator = $this->getMockBuilder('\Symfony\Component\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();

        $stubTranslator->expects($this->any())
            ->method('trans')
            ->will($this->returnCallback((array($this, 'callbackI18n'))));

        $actual = Operators::getI18n($stubTranslator, Operators::INFERIOR);
        $expected = 'inferior to';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::INFERIOR_OR_EQUAL);
        $expected = 'inferior or equal to';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::EQUAL);
        $expected = 'equal to';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::SUPERIOR_OR_EQUAL);
        $expected = 'superior or equal to';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::SUPERIOR);
        $expected = 'superior to';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::DIFFERENT);
        $expected = 'different from';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::IN);
        $expected = 'in';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, Operators::OUT);
        $expected = 'not in';
        $this->assertEquals($expected, $actual);

        $actual = Operators::getI18n($stubTranslator, 'unexpected operator');
        $expected = 'unexpected operator';
        $this->assertEquals($expected, $actual);
    }


//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorInferiorValidBefore()
//    {
//        $adapter = new BaseAdapter();
//        // Given
//        $a = 11;
//        $operator = Operators::INFERIOR;
//        $b = new QuantityParam($adapter, 12);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertTrue($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorInferiorInvalidEquals()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 12;
//        $operator = Operators::INFERIOR;
//        $b = new QuantityParam($adapter, 12);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertFalse($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorInferiorInvalidAfter()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 13;
//        $operator = Operators::INFERIOR;
//        $b = new QuantityParam($adapter, 12);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertFalse($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorInferiorOrEqualValidEqual()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 11;
//        $operator = Operators::INFERIOR_OR_EQUAL;
//        $b = new QuantityParam($adapter, 11);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertTrue($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorInferiorOrEqualValidBefore()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 10;
//        $operator = Operators::INFERIOR_OR_EQUAL;
//        $b = new QuantityParam($adapter, 11);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertTrue($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorInferiorOrEqualInValidAfter()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 12;
//        $operator = Operators::INFERIOR_OR_EQUAL;
//        $b = new QuantityParam($adapter, 11);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertFalse($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorEqualValidEqual()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 12;
//        $operator = Operators::EQUAL;
//        $b = new QuantityParam($adapter, 12);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertTrue($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorEqualInValidBefore()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 11;
//        $operator = Operators::EQUAL;
//        $b = new QuantityParam($adapter, 12);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertFalse($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorEqualInValidAfter()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 13;
//        $operator = Operators::EQUAL;
//        $b = new QuantityParam($adapter, 12);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertFalse($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorSuperiorOrEqualValidEqual()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 13;
//        $operator = Operators::SUPERIOR_OR_EQUAL;
//        $b = new QuantityParam($adapter, 13);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertTrue($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorSuperiorOrEqualAfter()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 14;
//        $operator = Operators::SUPERIOR_OR_EQUAL;
//        $b = new QuantityParam($adapter, 13);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertTrue($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorSuperiorOrEqualInvalidBefore()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 12;
//        $operator = Operators::SUPERIOR_OR_EQUAL;
//        $b = new QuantityParam($adapter, 13);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertFalse($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorSuperiorValidAfter()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 13;
//        $operator = Operators::SUPERIOR;
//        $b = new QuantityParam($adapter, 12);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertTrue($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorSuperiorInvalidEqual()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 12;
//        $operator = Operators::SUPERIOR;
//        $b = new QuantityParam($adapter, 12);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertFalse($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorSuperiorInvalidBefore()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 11;
//        $operator = Operators::SUPERIOR;
//        $b = new QuantityParam($adapter, 12);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertFalse($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorDifferentValid()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 12;
//        $operator = Operators::DIFFERENT;
//        $b = new QuantityParam($adapter, 11);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertTrue($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorDifferentInvalidEquals()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 11;
//        $operator = Operators::DIFFERENT;
//        $b = new QuantityParam($adapter, 11);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertFalse($actual);
//    }
//
//    /**
//     *
//     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
//     *
//     */
//    public function testOperatorInValid()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $a = 12;
//        $operator = 'X';
//        $b = new QuantityParam($adapter, 11);
//
//        // When
//        $actual = Operators::isValid($a, $operator, $b);
//
//        // Then
//        $this->assertFalse($actual);
//    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    function callbackI18n() {
        $args = func_get_args();

        return $args[0];
    }
}


