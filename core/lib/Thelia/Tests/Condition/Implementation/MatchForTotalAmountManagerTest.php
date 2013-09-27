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

namespace Thelia\Condition\Implementation;

use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\Operators;
use Thelia\Coupon\AdapterInterface;
use Thelia\Exception\InvalidConditionValueException;
use Thelia\Model\Currency;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test MatchForTotalAmountManager Class
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class MatchForTotalAmountManagerTest extends \PHPUnit_Framework_TestCase
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
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\BaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue($cartTotalPrice));

        $stubAdapter->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue($checkoutCurrency));

        $stubAdapter->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

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
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::setValidators
     * @expectedException \Thelia\Exception\InvalidConditionOperatorException
     *
     */
    public function testInValidBackOfficeInputOperator()
    {
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

        /** @var AdapterInterface $stubAdapter */
        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::IN,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => '400',
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::setValidators
     * @expectedException \Thelia\Exception\InvalidConditionOperatorException
     *
     */
    public function testInValidBackOfficeInputOperator2()
    {
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

        /** @var AdapterInterface $stubAdapter */
        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmountManager::INPUT2 => Operators::INFERIOR
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => '400',
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::setValidators
     * @expectedException \Thelia\Exception\InvalidConditionValueException
     *
     */
    public function testInValidBackOfficeInputValue()
    {
        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 'X',
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::setValidators
     * @expectedException \Thelia\Exception\InvalidConditionValueException
     *
     */
    public function testInValidBackOfficeInputValue2()
    {
        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400,
            MatchForTotalAmountManager::INPUT2 => 'FLA');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::isMatching
     *
     */
    public function testMatchingConditionInferior()
    {
        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::INFERIOR,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::isMatching
     *
     */
    public function testNotMatchingConditionInferior()
    {
        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->generateAdapterStub(400, 'EUR');

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::INFERIOR,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::isMatching
     *
     */
    public function testMatchingConditionInferiorEquals()
    {
        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->generateAdapterStub(400, 'EUR');

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::INFERIOR_OR_EQUAL,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::isMatching
     *
     */
    public function testMatchingConditionInferiorEquals2()
    {
        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::INFERIOR_OR_EQUAL,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::isMatching
     *
     */
    public function testNotMatchingConditionInferiorEquals()
    {
        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->generateAdapterStub(401, 'EUR');

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::INFERIOR_OR_EQUAL,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test equals operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::isMatching
     *
     */
    public function testMatchingConditionEqual()
    {
        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->generateAdapterStub(400, 'EUR');

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::EQUAL,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test equals operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::isMatching
     *
     */
    public function testNotMatchingConditionEqual()
    {
        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::EQUAL,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::isMatching
     *
     */
    public function testMatchingConditionSuperiorEquals()
    {
        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->generateAdapterStub(401, 'EUR');

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR_OR_EQUAL,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::isMatching
     *
     */
    public function testMatchingConditionSuperiorEquals2()
    {
        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->generateAdapterStub(400, 'EUR');

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR_OR_EQUAL,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::isMatching
     *
     */
    public function testNotMatchingConditionSuperiorEquals()
    {
        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR_OR_EQUAL,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::isMatching
     *
     */
    public function testMatchingConditionSuperior()
    {
        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->generateAdapterStub(401, 'EUR');

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::isMatching
     *
     */
    public function testNotMatchingConditionSuperior()
    {
        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->generateAdapterStub(399, 'EUR');

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check currency is checked
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::isMatching
     *
     */
    public function testMatchingConditionCurrency()
    {
        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->generateAdapterStub(400, 'EUR');

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::EQUAL,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check currency is checked
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmountManager::isMatching
     *
     */
    public function testNotMatchingConditionCurrency()
    {
        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->generateAdapterStub(400.00, 'EUR');

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::EQUAL,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'USD');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

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
