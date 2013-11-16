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
use Thelia\Coupon\FacadeInterface;
use Thelia\Coupon\ConditionCollection;
use Thelia\Model\CurrencyQuery;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test ConditionFactory Class
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class ConditionFactoryTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    public function setUp()
    {
    }

    /**
     * Check the Rules serialization module
     */
    public function testBuild()
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
        $stubContainer->expects($this->any())
            ->method('get')
            ->will($this->returnValue(new MatchForTotalAmountManager($stubFacade)));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));

        $condition1 = new MatchForTotalAmountManager($stubFacade);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 40.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR'
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $conditionFactory = new ConditionFactory($stubContainer);
        $ruleManager1 = $conditionFactory->build($condition1->getServiceId(), $operators, $values);

        $expected = $condition1;
        $actual = $ruleManager1;

        $this->assertEquals($expected, $actual);
        $this->assertEquals($condition1->getServiceId(), $ruleManager1->getServiceId());
        $this->assertEquals($condition1->getValidators(), $ruleManager1->getValidators());
    }

    /**
     * Check the Rules serialization module
     */
    public function testBuildFail()
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
        $stubContainer->expects($this->any())
            ->method('get')
            ->will($this->returnValue(new MatchForTotalAmountManager($stubFacade)));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValueMap(array('unset.service', false)));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));

        $condition1 = new MatchForTotalAmountManager($stubFacade);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 40.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR'
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $conditionFactory = new ConditionFactory($stubContainer);
        $conditionManager1 = $conditionFactory->build('unset.service', $operators, $values);

        $expected = false;
        $actual = $conditionManager1;

        $this->assertEquals($expected, $actual);
    }

    /**
     * Check the Rules serialization module
     */
    public function testRuleSerialisation()
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
        $stubContainer->expects($this->any())
            ->method('get')
            ->will($this->returnValue(new MatchForTotalAmountManager($stubFacade)));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));

        $condition1 = new MatchForTotalAmountManager($stubFacade);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 40.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR'
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $condition2 = new MatchForTotalAmountManager($stubFacade);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 400.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR'
        );
        $condition2->setValidatorsFromForm($operators, $values);

        $conditions = new ConditionCollection();
        $conditions->add($condition1);
        $conditions->add($condition2);

        $conditionFactory = new ConditionFactory($stubContainer);

        $serializedConditions = $conditionFactory->serializeConditionCollection($conditions);
        $unserializedConditions = $conditionFactory->unserializeConditionCollection($serializedConditions);

        $expected = (string) $conditions;
        $actual = (string) $unserializedConditions;

        $this->assertEquals($expected, $actual);
    }

    /**
     * Check the getInputs method
     */
    public function testGetInputs()
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
        $condition1 = new MatchForTotalAmountManager($stubFacade);
        $stubContainer->expects($this->any())
            ->method('get')
            ->will($this->returnValue($condition1));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));

        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 40.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR'
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $conditions = new ConditionCollection();
        $conditions->add($condition1);

        $conditionFactory = new ConditionFactory($stubContainer);

        $expected = $condition1->getValidators();
        $actual = $conditionFactory->getInputs('thelia.condition.match_for_x_articles');

        $this->assertEquals($expected, $actual);

    }

    /**
     * Check the getInputs method
     */
    public function testGetInputsFalse()
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
        $condition1 = new MatchForTotalAmountManager($stubFacade);
        $stubContainer->expects($this->any())
            ->method('get')
            ->will($this->returnValue($condition1));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValue(false));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));

        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 40.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR'
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $conditions = new ConditionCollection();
        $conditions->add($condition1);

        $conditionFactory = new ConditionFactory($stubContainer);

        $expected = false;
        $actual = $conditionFactory->getInputs('thelia.condition.unknown');

        $this->assertEquals($expected, $actual);

    }

    /**
     * Test condition serialization if collection is empty
     *
     * @covers Thelia\Condition\ConditionFactory::serializeConditionCollection
     */
    public function testSerializeConditionCollectionEmpty()
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
        $stubContainer->expects($this->any())
            ->method('get')
            ->will($this->returnValue(new MatchForEveryoneManager($stubFacade)));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));


        $conditions = new ConditionCollection();

        $conditionFactory = new ConditionFactory($stubContainer);


        $conditionNone = new MatchForEveryoneManager($stubFacade);
        $expectedCollection = new ConditionCollection();
        $expectedCollection->add($conditionNone);

        $expected = $conditionFactory->serializeConditionCollection($expectedCollection);
        $actual = $conditionFactory->serializeConditionCollection($conditions);

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
