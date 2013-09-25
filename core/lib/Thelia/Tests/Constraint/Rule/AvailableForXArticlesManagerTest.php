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

use Thelia\Constraint\ConditionValidator;
use Thelia\Constraint\Rule\AvailableForXArticlesManager;
use Thelia\Constraint\Rule\Operators;
use Thelia\Constraint\Rule\SerializableRule;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test MatchForXArticlesManager Class
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class AvailableForXArticlesManagerTest extends \PHPUnit_Framework_TestCase
{

//    /** @var AdapterInterface $stubTheliaAdapter */
//    protected $stubTheliaAdapter = null;

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
     * @covers Thelia\Coupon\Rule\AvailableForXArticlesManager::setValidators
     * @expectedException \Thelia\Exception\InvalidConditionOperatorException
     */
    public function testInValidBackOfficeInputOperator()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::IN
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 5
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Coupon\Rule\AvailableForXArticlesManager::setValidators
     * @expectedException \Thelia\Exception\InvalidConditionValueException
     */
    public function testInValidBackOfficeInputValue()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 'X'
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testMatchingRuleInferior()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::INFERIOR
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 5
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testNotMatchingRuleInferior()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::INFERIOR
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 4,
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testMatchingRuleInferiorEquals()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::INFERIOR_OR_EQUAL,
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 5,
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testMatchingRuleInferiorEquals2()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::INFERIOR_OR_EQUAL
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 4
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testNotMatchingRuleInferiorEquals()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::INFERIOR_OR_EQUAL
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 3
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test equals operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testMatchingRuleEqual()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::EQUAL
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 4
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test equals operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testNotMatchingRuleEqual()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::EQUAL
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 5
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testMatchingRuleSuperiorEquals()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 4
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testMatchingRuleSuperiorEquals2()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 3
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testNotMatchingRuleSuperiorEquals()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 5
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testMatchingRuleSuperior()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 3
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testNotMatchingRuleSuperior()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 4
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    public function testGetSerializableRule()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 4
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $serializableRule = $rule1->getSerializableRule();

        $expected = new SerializableRule();
        $expected->ruleServiceId = $rule1->getServiceId();
        $expected->operators = $operators;
        $expected->values = $values;

        $actual = $serializableRule;

        $this->assertEquals($expected, $actual);

    }

    public function testGetAvailableOperators()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 4
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $expected = array(
            AvailableForXArticlesManager::INPUT1 => array(
                Operators::INFERIOR,
                Operators::INFERIOR_OR_EQUAL,
                Operators::EQUAL,
                Operators::SUPERIOR_OR_EQUAL,
                Operators::SUPERIOR
            )
        );
        $actual = $rule1->getAvailableOperators();

        $this->assertEquals($expected, $actual);

    }

//    public function testGetValidators()
//    {
//        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\BaseAdapter')
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        $stubAdapter->expects($this->any())
//            ->method('getNbArticlesInCart')
//            ->will($this->returnValue(4));
//
//        $rule1 = new MatchForXArticlesManager($stubAdapter);
//        $operators = array(
//            MatchForXArticlesManager::INPUT1 => Operators::SUPERIOR
//        );
//        $values = array(
//            MatchForXArticlesManager::INPUT1 => 4
//        );
//        $rule1->setValidatorsFromForm($operators, $values);
//
//        $expected = array(
//            $operators,
//            $values
//        );
//        $actual = $rule1->getValidators();
//
//        $this->assertEquals($expected, $actual);
//
//    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

}
