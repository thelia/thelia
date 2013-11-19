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
use Thelia\Coupon\FacadeInterface;
use Thelia\Coupon\ConditionCollection;
use Thelia\Model\CurrencyQuery;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test ConditionEvaluator Class
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class ConditionEvaluatorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    public function setUp()
    {
    }

    public function testTestSuccess1Rules()
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

        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\Container')
            ->disableOriginalConstructor()
            ->getMock();

        $stubMatchForTotalAmountManager = $this->getMockBuilder('\Thelia\Condition\Implementation\MatchForTotalAmountManager')
            ->disableOriginalConstructor()
            ->getMock();
        $stubMatchForTotalAmountManager->expects($this->any())
            ->method('isMatching')
            ->will($this->returnValue(true));

        $stubContainer->expects($this->any())
            ->method('get')
            ->will($this->returnValue($stubMatchForTotalAmountManager));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));
        $stubFacade->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubFacade->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(401.00));

        $condition1 = new MatchForTotalAmountManager($stubFacade);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => '>',
            MatchForTotalAmountManager::INPUT2 => '=='
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $conditions = new ConditionCollection();
        $conditions->add($condition1);

        $conditionEvaluator = new ConditionEvaluator();
        $isValid = $conditionEvaluator->isMatching($conditions);

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    public function testTestFail1Rules()
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

        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\Container')
            ->disableOriginalConstructor()
            ->getMock();

        $stubMatchForTotalAmountManager = $this->getMockBuilder('\Thelia\Condition\Implementation\MatchForTotalAmountManager')
            ->disableOriginalConstructor()
            ->getMock();
        $stubMatchForTotalAmountManager->expects($this->any())
            ->method('isMatching')
            ->will($this->returnValue(true));

        $stubContainer->expects($this->any())
            ->method('get')
            ->will($this->returnValue($stubMatchForTotalAmountManager));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));
        $stubFacade->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubFacade->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(400.00));

        $condition1 = new MatchForTotalAmountManager($stubFacade);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => '>',
            MatchForTotalAmountManager::INPUT2 => '=='
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $conditions = new ConditionCollection();
        $conditions->add($condition1);

        $conditionEvaluator = new ConditionEvaluator();
        $isValid = $conditionEvaluator->isMatching($conditions);

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual, 'Conditions evaluator always think Customer is matching conditions');
    }

    public function testTestSuccess2Rules()
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

        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\Container')
            ->disableOriginalConstructor()
            ->getMock();

        $stubMatchForTotalAmountManager = $this->getMockBuilder('\Thelia\Condition\Implementation\MatchForTotalAmountManager')
            ->disableOriginalConstructor()
            ->getMock();
        $stubMatchForTotalAmountManager->expects($this->any())
            ->method('isMatching')
            ->will($this->returnValue(true));

        $stubContainer->expects($this->any())
            ->method('get')
            ->will($this->returnValue($stubMatchForTotalAmountManager));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));
        $stubFacade->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubFacade->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(401.00));
        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(5));

        $condition1 = new MatchForTotalAmountManager($stubFacade);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => '>',
            MatchForTotalAmountManager::INPUT2 => '=='
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $condition2 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => '>'
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 4
        );
        $condition2->setValidatorsFromForm($operators, $values);

        $conditions = new ConditionCollection();
        $conditions->add($condition1);
        $conditions->add($condition2);

        $conditionEvaluator = new ConditionEvaluator();
        $isValid = $conditionEvaluator->isMatching($conditions);

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    public function testTestFail2Rules()
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

        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\Container')
            ->disableOriginalConstructor()
            ->getMock();

        $stubMatchForTotalAmountManager = $this->getMockBuilder('\Thelia\Condition\Implementation\MatchForTotalAmountManager')
            ->disableOriginalConstructor()
            ->getMock();
        $stubMatchForTotalAmountManager->expects($this->any())
            ->method('isMatching')
            ->will($this->returnValue(true));

        $stubContainer->expects($this->any())
            ->method('get')
            ->will($this->returnValue($stubMatchForTotalAmountManager));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));
        $stubFacade->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue('EUR'));
        $stubFacade->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue(400.00));
        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(5));

        $condition1 = new MatchForTotalAmountManager($stubFacade);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => '>',
            MatchForTotalAmountManager::INPUT2 => '=='
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR');
        $condition1->setValidatorsFromForm($operators, $values);

        $condition2 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => '>'
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 4
        );
        $condition2->setValidatorsFromForm($operators, $values);

        $conditions = new ConditionCollection();
        $conditions->add($condition1);
        $conditions->add($condition2);

        $conditionEvaluator = new ConditionEvaluator();
        $isValid = $conditionEvaluator->isMatching($conditions);

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual, 'Conditions evaluator always think Customer is matching conditions');
    }

    public function testVariableOpComparisonSuccess()
    {
        $conditionEvaluator = new ConditionEvaluator();
        $expected = true;
        $actual = $conditionEvaluator->variableOpComparison(1, Operators::EQUAL, 1);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::DIFFERENT, 2);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::SUPERIOR, 0);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::INFERIOR, 2);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::INFERIOR_OR_EQUAL, 1);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::INFERIOR_OR_EQUAL, 2);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::SUPERIOR_OR_EQUAL, 1);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::SUPERIOR_OR_EQUAL, 0);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::IN, array(1, 2, 3));
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::OUT, array(0, 2, 3));
        $this->assertEquals($expected, $actual);

    }

    public function testVariableOpComparisonFail()
    {
        $conditionEvaluator = new ConditionEvaluator();
        $expected = false;
        $actual = $conditionEvaluator->variableOpComparison(2, Operators::EQUAL, 1);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(2, Operators::DIFFERENT, 2);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(0, Operators::SUPERIOR, 0);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(3, Operators::INFERIOR, 2);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(2, Operators::INFERIOR_OR_EQUAL, 1);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(3, Operators::SUPERIOR_OR_EQUAL, 4);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(0, Operators::IN, array(1, 2, 3));
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(2, Operators::OUT, array(0, 2, 3));
        $this->assertEquals($expected, $actual);

    }

    /**
     * @expectedException \Exception
     */
    public function testVariableOpComparisonException()
    {
        $conditionEvaluator = new ConditionEvaluator();
        $expected = true;
        $actual = $conditionEvaluator->variableOpComparison(1, 'bad', 1);
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
