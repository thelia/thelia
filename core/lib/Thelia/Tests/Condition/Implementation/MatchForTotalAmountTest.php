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
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Operators;
use Thelia\Condition\ConditionCollection;
use Thelia\Coupon\FacadeInterface;
use Thelia\Exception\InvalidConditionValueException;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;

/**
 * Unit Test MatchForTotalAmount Class
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class MatchForTotalAmountTest extends \PHPUnit_Framework_TestCase
{
    /** @var FacadeInterface $stubTheliaAdapter */
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
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue($cartTotalPrice));

        $stubFacade->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue($checkoutCurrency));

        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $currency1 = new Currency();
        $currency1->setCode('EUR');
        $currency2 = new Currency();
        $currency2->setCode('USD');
        $stubFacade->expects($this->any())
            ->method('getAvailableCurrencies')
            ->will($this->returnValue(array($currency1, $currency2)));

        return $stubFacade;
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
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::setValidators
     * @expectedException \Thelia\Exception\InvalidConditionOperatorException
     *
     */
    public function testInValidBackOfficeInputOperator()
    {
        $stubFacade = $this->generateAdapterStub(399, 'EUR');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::IN,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => '400',
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::setValidators
     * @expectedException \Thelia\Exception\InvalidConditionOperatorException
     *
     */
    public function testInValidBackOfficeInputOperator2()
    {
        $stubFacade = $this->generateAdapterStub(399, 'EUR');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmount::INPUT2 => Operators::INFERIOR
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => '400',
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::setValidators
     * @expectedException \Thelia\Exception\InvalidConditionValueException
     *
     */
    public function testInValidBackOfficeInputValue()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateAdapterStub(399, 'EUR');

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 'X',
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::setValidators
     * @expectedException \Thelia\Exception\InvalidConditionValueException
     *
     */
    public function testInValidBackOfficeInputValue2()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateAdapterStub(399, 'EUR');

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400,
            MatchForTotalAmount::INPUT2 => 'FLA');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::isMatching
     *
     */
    public function testMatchingConditionInferior()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateAdapterStub(399, 'EUR');

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::INFERIOR,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::isMatching
     *
     */
    public function testNotMatchingConditionInferior()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateAdapterStub(400, 'EUR');

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::INFERIOR,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::isMatching
     *
     */
    public function testMatchingConditionInferiorEquals()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateAdapterStub(400, 'EUR');

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::INFERIOR_OR_EQUAL,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::isMatching
     *
     */
    public function testMatchingConditionInferiorEquals2()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateAdapterStub(399, 'EUR');

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::INFERIOR_OR_EQUAL,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::isMatching
     *
     */
    public function testNotMatchingConditionInferiorEquals()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateAdapterStub(401, 'EUR');

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::INFERIOR_OR_EQUAL,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test equals operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::isMatching
     *
     */
    public function testMatchingConditionEqual()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateAdapterStub(400, 'EUR');

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::EQUAL,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test equals operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::isMatching
     *
     */
    public function testNotMatchingConditionEqual()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateAdapterStub(399, 'EUR');

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::EQUAL,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::isMatching
     *
     */
    public function testMatchingConditionSuperiorEquals()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateAdapterStub(401, 'EUR');

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::SUPERIOR_OR_EQUAL,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::isMatching
     *
     */
    public function testMatchingConditionSuperiorEquals2()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateAdapterStub(400, 'EUR');

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::SUPERIOR_OR_EQUAL,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::isMatching
     *
     */
    public function testNotMatchingConditionSuperiorEquals()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateAdapterStub(399, 'EUR');

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::SUPERIOR_OR_EQUAL,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::isMatching
     *
     */
    public function testMatchingConditionSuperior()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateAdapterStub(401, 'EUR');

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::isMatching
     *
     */
    public function testNotMatchingConditionSuperior()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateAdapterStub(399, 'EUR');

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check currency is checked
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::isMatching
     *
     */
    public function testMatchingConditionCurrency()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateAdapterStub(400, 'EUR');

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::EQUAL,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check currency is checked
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::isMatching
     *
     */
    public function testNotMatchingConditionCurrency()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->generateAdapterStub(400.00, 'EUR');

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::EQUAL,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'USD');
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check unknown currency
     *
     * @covers Thelia\Condition\Implementation\ConditionAbstract::isCurrencyValid
     * @expectedException \Thelia\Exception\InvalidConditionValueException
     *
     */
    public function testUnknownCurrencyCode()
    {
        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();

        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getTranslator')
            ->will($this->returnValue($stubTranslator));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $currencies = CurrencyQuery::create();
        $currencies = $currencies->find();
        $stubFacade->expects($this->any())
            ->method('getAvailableCurrencies')
            ->will($this->returnValue($currencies));


        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::EQUAL,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'UNK');
        $condition1->setValidatorsFromForm($operators, $values);


        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\Container')
            ->disableOriginalConstructor()
            ->getMock();
        $stubContainer->expects($this->any())
            ->method('get')
            ->will($this->returnValue($condition1));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));

        $conditionFactory = new ConditionFactory($stubContainer);

        $collection = new ConditionCollection();
        $collection[] = $condition1;

        $serialized = $conditionFactory->serializeConditionCollection($collection);
        $conditionFactory->unserializeConditionCollection($serialized);
    }

    /**
     * Check invalid currency
     *
     * @covers Thelia\Condition\Implementation\ConditionAbstract::isPriceValid
     * @expectedException \Thelia\Exception\InvalidConditionValueException
     *
     */
    public function testInvalidCurrencyValue()
    {
        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();

        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getTranslator')
            ->will($this->returnValue($stubTranslator));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $currencies = CurrencyQuery::create();
        $currencies = $currencies->find();
        $stubFacade->expects($this->any())
            ->method('getAvailableCurrencies')
            ->will($this->returnValue($currencies));


        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::EQUAL,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 'notfloat',
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);


        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\Container')
            ->disableOriginalConstructor()
            ->getMock();
        $stubContainer->expects($this->any())
            ->method('get')
            ->will($this->returnValue($condition1));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));

        $conditionFactory = new ConditionFactory($stubContainer);

        $collection = new ConditionCollection();
        $collection[] = $condition1;

        $serialized = $conditionFactory->serializeConditionCollection($collection);
        $conditionFactory->unserializeConditionCollection($serialized);
    }

    /**
     * Check invalid currency
     *
     * @covers Thelia\Condition\Implementation\ConditionAbstract::isPriceValid
     * @expectedException \Thelia\Exception\InvalidConditionValueException
     *
     */
    public function testPriceAsZero()
    {
        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();

        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getTranslator')
            ->will($this->returnValue($stubTranslator));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $currencies = CurrencyQuery::create();
        $currencies = $currencies->find();
        $stubFacade->expects($this->any())
            ->method('getAvailableCurrencies')
            ->will($this->returnValue($currencies));


        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::EQUAL,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 0.00,
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);


        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\Container')
            ->disableOriginalConstructor()
            ->getMock();
        $stubContainer->expects($this->any())
            ->method('get')
            ->will($this->returnValue($condition1));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));

        $conditionFactory = new ConditionFactory($stubContainer);

        $collection = new ConditionCollection();
        $collection[] = $condition1;

        $serialized = $conditionFactory->serializeConditionCollection($collection);
        $conditionFactory->unserializeConditionCollection($serialized);
    }

    /**
     * Generate adapter stub
     *
     * @param int    $cartTotalPrice   Cart total price
     * @param string $checkoutCurrency Checkout currency
     * @param string $i18nOutput       Output from each translation
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function generateFacadeStub($cartTotalPrice = 400, $checkoutCurrency = 'EUR', $i18nOutput = '')
    {
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue($cartTotalPrice));

        $stubFacade->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue($checkoutCurrency));

        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();
        $stubTranslator->expects($this->any())
            ->method('trans')
            ->will($this->returnValue($i18nOutput));

        $stubFacade->expects($this->any())
            ->method('getTranslator')
            ->will($this->returnValue($stubTranslator));

        $currency1 = new Currency();
        $currency1->setCode('EUR');
        $currency2 = new Currency();
        $currency2->setCode('USD');
        $stubFacade->expects($this->any())
            ->method('getAvailableCurrencies')
            ->will($this->returnValue(array($currency1, $currency2)));

        return $stubFacade;
    }

    /**
     * Check getName i18n
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::getName
     *
     */
    public function testGetName()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'Cart total amount');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchForTotalAmount($stubFacade);

        $actual = $condition1->getName();
        $expected = 'Cart total amount';
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check tooltip i18n
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::getToolTip
     *
     */
    public function testGetToolTip()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'If cart total amount is <strong>%operator%</strong> %amount% %currency%');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::EQUAL,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $actual = $condition1->getToolTip();
        $expected = 'If cart total amount is <strong>%operator%</strong> %amount% %currency%';
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check validator
     *
     * @covers Thelia\Condition\Implementation\MatchForTotalAmount::generateInputs
     *
     */
    public function testGetValidator()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'Price');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::INPUT1 => Operators::EQUAL,
            MatchForTotalAmount::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::INPUT1 => 400.00,
            MatchForTotalAmount::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $actual = $condition1->getValidators();

        $validators = array(
            'inputs' => array(
                MatchForTotalAmount::INPUT1 => array(
                    'availableOperators' => array(
                        '<' => 'Price',
                        '<=' => 'Price',
                        '==' => 'Price',
                        '>=' => 'Price',
                        '>' => 'Price'
                    ),
                    'availableValues' => '',
                    'value' => '',
                    'selectedOperator' => ''
                ),
                MatchForTotalAmount::INPUT2 => array(
                    'availableOperators' => array('==' => 'Price'),
                    'availableValues' => array(
                        'EUR' => '€',
                        'USD' => '$',
                        'GBP' => '£',
                    ),
                    'value' => '',
                    'selectedOperator' => Operators::EQUAL
                )
            ),
            'setOperators' => array(
                'price' => '==',
                'currency' => '=='
            ),
            'setValues' => array(
                'price' => 400,
                'currency' => 'EUR'
            )
        );
        $expected = $validators;

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
