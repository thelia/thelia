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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Operators;
use Thelia\Coupon\AdapterInterface;

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
        $stubContainer = $this->getMockBuilder('\Symfony\Component\DependencyInjection\Container')
            ->disableOriginalConstructor()
            ->getMock();



        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();

        /** @var AdapterInterface $stubAdapter */
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\BaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getTranslator')
            ->will($this->returnValue($stubTranslator));

        $condition1 = new MatchForTotalAmountManager($stubAdapter);
        $operators = array(
            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            MatchForTotalAmountManager::INPUT1 => 40.00,
            MatchForTotalAmountManager::INPUT2 => 'EUR'
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $conditionFactory = new ConditionFactory($this->getContainer());
        $ruleManager1 = $conditionFactory->build($condition1->getServiceId(), $operators, $values);

        $expected = $condition1;
        $actual = $ruleManager1;

        $this->assertEquals($expected, $actual);
        $this->assertEquals($condition1->getServiceId(), $ruleManager1->getServiceId());
        $this->assertEquals($condition1->getValidators(), $ruleManager1->getValidators());
    }

//    /**
//     * Check the Rules serialization module
//     */
//    public function testBuildFail()
//    {
//        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        /** @var AdapterInterface $stubAdapter */
//        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\BaseAdapter')
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        $stubAdapter->expects($this->any())
//            ->method('getTranslator')
//            ->will($this->returnValue($stubTranslator));
//
//        $condition1 = new MatchForTotalAmountManager($stubAdapter);
//        $operators = array(
//            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
//            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
//        );
//        $values = array(
//            MatchForTotalAmountManager::INPUT1 => 40.00,
//            MatchForTotalAmountManager::INPUT2 => 'EUR'
//        );
//        $condition1->setValidatorsFromForm($operators, $values);
//
//        $conditionFactory = new ConditionFactory($this->getContainer());
//        $conditionManager1 = $conditionFactory->build('unset.service', $operators, $values);
//
//        $expected = false;
//        $actual = $conditionManager1;
//
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Check the Rules serialization module
//     */
//    public function testRuleSerialisation()
//    {
//        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        /** @var AdapterInterface $stubAdapter */
//        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\BaseAdapter')
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        $stubAdapter->expects($this->any())
//            ->method('getTranslator')
//            ->will($this->returnValue($stubTranslator));
//
//        $condition1 = new MatchForTotalAmountManager($stubAdapter);
//        $operators = array(
//            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
//            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
//        );
//        $values = array(
//            MatchForTotalAmountManager::INPUT1 => 40.00,
//            MatchForTotalAmountManager::INPUT2 => 'EUR'
//        );
//        $condition1->setValidatorsFromForm($operators, $values);
//
//        $condition2 = new MatchForTotalAmountManager($stubAdapter);
//        $operators = array(
//            MatchForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
//            MatchForTotalAmountManager::INPUT2 => Operators::EQUAL
//        );
//        $values = array(
//            MatchForTotalAmountManager::INPUT1 => 400.00,
//            MatchForTotalAmountManager::INPUT2 => 'EUR'
//        );
//        $condition2->setValidatorsFromForm($operators, $values);
//
//        $conditions = new ConditionCollection();
//        $conditions->add($condition1);
//        $conditions->add($condition2);
//
//        $conditionFactory = new ConditionFactory($this->getContainer());
//
//        $serializedConditions = $conditionFactory->serializeConditionCollection($conditions);
//        $unserializedConditions = $conditionFactory->unserializeConditionCollection($serializedConditions);
//
//        $expected = (string) $conditions;
//        $actual = (string) $unserializedConditions;
//
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Get Mocked Container with 2 Rules
//     *
//     * @return ContainerBuilder
//     */
//    public function getContainer()
//    {
//        $container = new ContainerBuilder();
//
//        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        /** @var AdapterInterface $stubAdapter */
//        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\BaseAdapter')
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        $stubAdapter->expects($this->any())
//            ->method('getTranslator')
//            ->will($this->returnValue($stubTranslator));
//
//        $condition1 = new MatchForTotalAmountManager($stubAdapter);
//        $condition2 = new MatchForXArticlesManager($stubAdapter);
//
//        $adapter = new BaseAdapter($container);
//
//        $container->set('thelia.condition.match_for_total_amount', $condition1);
//        $container->set('thelia.condition.match_for_x_articles', $condition2);
//        $container->set('thelia.adapter', $adapter);
//
//        return $container;
//    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }
}
