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
use Thelia\Constraint\Rule\AvailableForTotalAmountManager;
use Thelia\Constraint\Rule\Operators;
use Thelia\Exception\InvalidConditionValueException;
use Thelia\Model\Currency;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test MatchForTotalAmountManager Class
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class AvailableForTotalAmountManagerTest extends \PHPUnit_Framework_TestCase
{
    /** @var AdapterInterface $stubTheliaAdapter */
    protected $stubTheliaAdapter = null;

    /**
     * Generate adapter stub
     *
     * @param int    $cartTotalPrice   Cart total price
     * @param string $checkoutCurrency Checkout currency
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function generateAdapterStub($cartTotalPrice = 400, $checkoutCurrency = 'EUR')
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue($cartTotalPrice));

        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue($checkoutCurrency));

        $stubAdapter->expects($this->any())
            ->method('getConstraintValidator')
            ->will($this->returnValue(new ConditionValidator()));

        $currency1 = new Currency();
        $currency1->setCode('EUR');
        $currency2 = new Currency();
        $currency2->setCode('USD');
        $stubAdapter->expects($this->any())
            ->method('getAvailableCurrencies')
            ->will($this->returnValue(array($currency1, $currency2)));

        return $stubAdapter;
    }

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
     * @expectedException \Thelia\Exception\InvalidConditionOperatorException
     *
     */
    public function testInValidBackOfficeInputOperator()
    {
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

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
     * @expectedException \Thelia\Exception\InvalidConditionOperatorException
     *
     */
    public function testInValidBackOfficeInputOperator2()
    {
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

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
     * @expectedException \Thelia\Exception\InvalidConditionValueException
     *
     */
    public function testInValidBackOfficeInputValue()
    {
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

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
     * @expectedException \Thelia\Exception\InvalidConditionValueException
     *
     */
    public function testInValidBackOfficeInputValue2()
    {
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

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
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

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
        $stubAdapter = $this->generateAdapterStub(400, 'EUR');

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
        $stubAdapter = $this->generateAdapterStub(400, 'EUR');

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
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

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
        $stubAdapter = $this->generateAdapterStub(401, 'EUR');

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
        $stubAdapter = $this->generateAdapterStub(400, 'EUR');

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
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

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
        $stubAdapter = $this->generateAdapterStub(401, 'EUR');

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
        $stubAdapter = $this->generateAdapterStub(400, 'EUR');

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
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

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
        $stubAdapter = $this->generateAdapterStub(401, 'EUR');

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
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

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
        $stubAdapter = $this->generateAdapterStub(400, 'EUR');

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
        $stubAdapter = $this->generateAdapterStub(400.00, 'EUR');

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
