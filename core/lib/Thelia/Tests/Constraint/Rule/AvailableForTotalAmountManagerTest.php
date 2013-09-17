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

use Thelia\Constraint\ConstraintValidator;
use Thelia\Constraint\Rule\AvailableForTotalAmountManager;
use Thelia\Constraint\Rule\Operators;
use Thelia\Exception\InvalidRuleValueException;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test AvailableForTotalAmountManager Class
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class AvailableForTotalAmountManagerTest extends \PHPUnit_Framework_TestCase
{
    /** @var CouponAdapterInterface $stubTheliaAdapter */
    protected $stubTheliaAdapter = null;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {

    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Coupon\Rule\AvailableForTotalAmountManager::setValidators
     * @expectedException \Thelia\Exception\InvalidRuleOperatorException
     *
     */
    public function testInValidBackOfficeInputOperator()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(399));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::IN,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => '400',
            AvailableForTotalAmountManager::INPUT2 => 'EUR');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Coupon\Rule\AvailableForTotalAmountManager::setValidators
     * @expectedException \Thelia\Exception\InvalidRuleOperatorException
     *
     */
    public function testInValidBackOfficeInputOperator2()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(399));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            AvailableForTotalAmountManager::INPUT2 => Operators::INFERIOR
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => '400',
            AvailableForTotalAmountManager::INPUT2 => 'EUR');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Coupon\Rule\AvailableForTotalAmountManager::setValidators
     * @expectedException \Thelia\Exception\InvalidRuleValueException
     *
     */
    public function testInValidBackOfficeInputValue()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(399));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 'X',
            AvailableForTotalAmountManager::INPUT2 => 'EUR');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Coupon\Rule\AvailableForTotalAmountManager::setValidators
     * @expectedException \Thelia\Exception\InvalidRuleValueException
     *
     */
    public function testInValidBackOfficeInputValue2()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(399));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 400,
            AvailableForTotalAmountManager::INPUT2 => 'FLA');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForTotalAmountManager::isMatching
     *
     */
    public function testMatchingRuleInferior()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(399));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::INFERIOR,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 400.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForTotalAmountManager::isMatching
     *
     */
    public function testNotMatchingRuleInferior()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(400));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::INFERIOR,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 400.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForTotalAmountManager::isMatching
     *
     */
    public function testMatchingRuleInferiorEquals()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(400));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::INFERIOR_OR_EQUAL,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 400.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForTotalAmountManager::isMatching
     *
     */
    public function testMatchingRuleInferiorEquals2()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(399));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::INFERIOR_OR_EQUAL,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 400.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForTotalAmountManager::isMatching
     *
     */
    public function testNotMatchingRuleInferiorEquals()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(401));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::INFERIOR_OR_EQUAL,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 400.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test equals operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForTotalAmountManager::isMatching
     *
     */
    public function testMatchingRuleEqual()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(400));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::EQUAL,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 400.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test equals operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForTotalAmountManager::isMatching
     *
     */
    public function testNotMatchingRuleEqual()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(399));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::EQUAL,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 400.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForTotalAmountManager::isMatching
     *
     */
    public function testMatchingRuleSuperiorEquals()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(401));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::SUPERIOR_OR_EQUAL,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 400.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForTotalAmountManager::isMatching
     *
     */
    public function testMatchingRuleSuperiorEquals2()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(400));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::SUPERIOR_OR_EQUAL,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 400.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForTotalAmountManager::isMatching
     *
     */
    public function testNotMatchingRuleSuperiorEquals()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(399.00));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::SUPERIOR_OR_EQUAL,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 400.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForTotalAmountManager::isMatching
     *
     */
    public function testMatchingRuleSuperior()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(401));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 400.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForTotalAmountManager::isMatching
     *
     */
    public function testNotMatchingRuleSuperior()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(399.00));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 400.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check currency is checked
     *
     * @covers Thelia\Constraint\Rule\AvailableForTotalAmountManager::isMatching
     *
     */
    public function testMatchingRuleCurrency()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(400.00));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::EQUAL,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 400.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check currency is checked
     *
     * @covers Thelia\Constraint\Rule\AvailableForTotalAmountManager::isMatching
     *
     */
    public function testNotMatchingRuleCurrency()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(400.00));
        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConstraintValidator()));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::EQUAL,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 400.00,
            AvailableForTotalAmountManager::INPUT2 => 'USD');
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

}
