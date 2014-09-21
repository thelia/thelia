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

namespace Thelia\Tests\Condition;

use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\MatchForEveryone;
use Thelia\Condition\Implementation\MatchForTotalAmount;
use Thelia\Condition\Operators;
use Thelia\Coupon\FacadeInterface;
use Thelia\Condition\ConditionCollection;
use Thelia\Model\CurrencyQuery;

/**
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
            ->will($this->returnValue(new MatchForTotalAmount($stubFacade)));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::CART_TOTAL => Operators::SUPERIOR,
            MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::CART_TOTAL => 40.00,
            MatchForTotalAmount::CART_CURRENCY => 'EUR'
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
            ->will($this->returnValue(new MatchForTotalAmount($stubFacade)));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValueMap(array('unset.service', false)));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::CART_TOTAL => Operators::SUPERIOR,
            MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::CART_TOTAL => 40.00,
            MatchForTotalAmount::CART_CURRENCY => 'EUR'
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
            ->will($this->returnValue(new MatchForTotalAmount($stubFacade)));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));

        $condition1 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::CART_TOTAL => Operators::SUPERIOR,
            MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::CART_TOTAL => 40.00,
            MatchForTotalAmount::CART_CURRENCY => 'EUR'
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $condition2 = new MatchForTotalAmount($stubFacade);
        $operators = array(
            MatchForTotalAmount::CART_TOTAL => Operators::SUPERIOR,
            MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::CART_TOTAL => 400.00,
            MatchForTotalAmount::CART_CURRENCY => 'EUR'
        );
        $condition2->setValidatorsFromForm($operators, $values);

        $conditions = new ConditionCollection();
        $conditions[] = $condition1;
        $conditions[] = $condition2;

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
        $condition1 = new MatchForTotalAmount($stubFacade);
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
            MatchForTotalAmount::CART_TOTAL => Operators::SUPERIOR,
            MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::CART_TOTAL => 40.00,
            MatchForTotalAmount::CART_CURRENCY => 'EUR'
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $conditions = new ConditionCollection();
        $conditions[] = $condition1;

        $conditionFactory = new ConditionFactory($stubContainer);

        $expected = $condition1->getValidators();
        $actual = $conditionFactory->getInputsFromServiceId('thelia.condition.match_for_x_articles');

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
        $condition1 = new MatchForTotalAmount($stubFacade);
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
            MatchForTotalAmount::CART_TOTAL => Operators::SUPERIOR,
            MatchForTotalAmount::CART_CURRENCY => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmount::CART_TOTAL => 40.00,
            MatchForTotalAmount::CART_CURRENCY => 'EUR'
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $conditions = new ConditionCollection();
        $conditions[] = $condition1;

        $conditionFactory = new ConditionFactory($stubContainer);

        $expected = false;
        $actual = $conditionFactory->getInputsFromServiceId('thelia.condition.unknown');

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
            ->will($this->returnValue(new MatchForEveryone($stubFacade)));

        $stubContainer->expects($this->any())
            ->method('has')
            ->will($this->returnValue(true));

        $stubFacade->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($stubContainer));

        $conditions = new ConditionCollection();

        $conditionFactory = new ConditionFactory($stubContainer);

        $conditionNone = new MatchForEveryone($stubFacade);
        $expectedCollection = new ConditionCollection();
        $expectedCollection[] = $conditionNone;

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
