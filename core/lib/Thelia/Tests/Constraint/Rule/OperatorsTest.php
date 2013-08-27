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

use Thelia\Constraint\Validator\QuantityParam;
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

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorInferiorValidBefore()
    {
        // Given
        $a = 11;
        $operator = Operators::INFERIOR;
        $b = new QuantityParam(, 12);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertTrue($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorInferiorInvalidEquals()
    {
        // Given
        $a = 12;
        $operator = Operators::INFERIOR;
        $b = new QuantityParam(, 12);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertFalse($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorInferiorInvalidAfter()
    {
        // Given
        $a = 13;
        $operator = Operators::INFERIOR;
        $b = new QuantityParam(, 12);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertFalse($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorInferiorOrEqualValidEqual()
    {
        // Given
        $a = 11;
        $operator = Operators::INFERIOR_OR_EQUAL;
        $b = new QuantityParam(, 11);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertTrue($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorInferiorOrEqualValidBefore()
    {
        // Given
        $a = 10;
        $operator = Operators::INFERIOR_OR_EQUAL;
        $b = new QuantityParam(, 11);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertTrue($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorInferiorOrEqualInValidAfter()
    {
        // Given
        $a = 12;
        $operator = Operators::INFERIOR_OR_EQUAL;
        $b = new QuantityParam(, 11);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertFalse($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorEqualValidEqual()
    {
        // Given
        $a = 12;
        $operator = Operators::EQUAL;
        $b = new QuantityParam(, 12);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertTrue($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorEqualInValidBefore()
    {
        // Given
        $a = 11;
        $operator = Operators::EQUAL;
        $b = new QuantityParam(, 12);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertFalse($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorEqualInValidAfter()
    {
        // Given
        $a = 13;
        $operator = Operators::EQUAL;
        $b = new QuantityParam(, 12);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertFalse($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorSuperiorOrEqualValidEqual()
    {
        // Given
        $a = 13;
        $operator = Operators::SUPERIOR_OR_EQUAL;
        $b = new QuantityParam(, 13);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertTrue($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorSuperiorOrEqualAfter()
    {
        // Given
        $a = 14;
        $operator = Operators::SUPERIOR_OR_EQUAL;
        $b = new QuantityParam(, 13);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertTrue($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorSuperiorOrEqualInvalidBefore()
    {
        // Given
        $a = 12;
        $operator = Operators::SUPERIOR_OR_EQUAL;
        $b = new QuantityParam(, 13);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertFalse($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorSuperiorValidAfter()
    {
        // Given
        $a = 13;
        $operator = Operators::SUPERIOR;
        $b = new QuantityParam(, 12);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertTrue($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorSuperiorInvalidEqual()
    {
        // Given
        $a = 12;
        $operator = Operators::SUPERIOR;
        $b = new QuantityParam(, 12);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertFalse($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorSuperiorInvalidBefore()
    {
        // Given
        $a = 11;
        $operator = Operators::SUPERIOR;
        $b = new QuantityParam(, 12);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertFalse($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorDifferentValid()
    {
        // Given
        $a = 12;
        $operator = Operators::DIFFERENT;
        $b = new QuantityParam(, 11);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertTrue($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorDifferentInvalidEquals()
    {
        // Given
        $a = 11;
        $operator = Operators::DIFFERENT;
        $b = new QuantityParam(, 11);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertFalse($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\Operator::isValidAccordingToOperator
     *
     */
    public function testOperatorInValid()
    {
        // Given
        $a = 12;
        $operator = 'X';
        $b = new QuantityParam(, 11);

        // When
        $actual = Operators::isValid($a, $operator, $b);

        // Then
        $this->assertFalse($actual);
    }



    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

}
