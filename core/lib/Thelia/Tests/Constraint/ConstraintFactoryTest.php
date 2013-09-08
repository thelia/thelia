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

namespace Thelia\Constraint;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Constraint\Rule\AvailableForTotalAmountManager;
use Thelia\Constraint\Rule\AvailableForXArticlesManager;
use Thelia\Constraint\Rule\Operators;
use Thelia\Coupon\CouponBaseAdapter;
use Thelia\Coupon\CouponRuleCollection;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test ConstraintManager Class
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class ConstraintFactoryTest extends \PHPUnit_Framework_TestCase
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

        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getTranslator')
            ->will($this->returnValue($stubTranslator));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 40.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR'
        );
        $rule1->setValidatorsFromForm($operators, $values);

        /** @var ConstraintManager $constraintManager */
        $constraintFactory = new ConstraintFactory($this->getContainer());
        $ruleManager1 = $constraintFactory->build($rule1->getServiceId(), $operators, $values);

        $expected = $rule1;
        $actual = $ruleManager1;

        $this->assertEquals($expected, $actual);
        $this->assertEquals($rule1->getServiceId(), $ruleManager1->getServiceId());
        $this->assertEquals($rule1->getValidators(), $ruleManager1->getValidators());
    }

    /**
     * Check the Rules serialization module
     */
    public function testBuildFail()
    {
        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getTranslator')
            ->will($this->returnValue($stubTranslator));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 40.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR'
        );
        $rule1->setValidatorsFromForm($operators, $values);

        /** @var ConstraintManager $constraintManager */
        $constraintFactory = new ConstraintFactory($this->getContainer());
        $ruleManager1 = $constraintFactory->build('unset.service', $operators, $values);

        $expected = false;
        $actual = $ruleManager1;

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

        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getTranslator')
            ->will($this->returnValue($stubTranslator));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 40.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR'
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $rule2 = new AvailableForTotalAmountManager($stubAdapter);
        $operators = array(
            AvailableForTotalAmountManager::INPUT1 => Operators::SUPERIOR,
            AvailableForTotalAmountManager::INPUT2 => Operators::EQUAL
        );
        $values = array(
            AvailableForTotalAmountManager::INPUT1 => 400.00,
            AvailableForTotalAmountManager::INPUT2 => 'EUR'
        );
        $rule2->setValidatorsFromForm($operators, $values);

        $rules = new CouponRuleCollection();
        $rules->add($rule1);
        $rules->add($rule2);

        /** @var ConstraintManager $constraintManager */
        $constraintFactory = new ConstraintFactory($this->getContainer());

        $serializedRules = $constraintFactory->serializeCouponRuleCollection($rules);
        $unserializedRules = $constraintFactory->unserializeCouponRuleCollection($serializedRules);

        $expected = (string)$rules;
        $actual = (string)$unserializedRules;

        $this->assertEquals($expected, $actual);
    }

    /**
     * Get Mocked Container with 2 Rules
     *
     * @return ContainerBuilder
     */
    public function getContainer()
    {
        $container = new ContainerBuilder();

        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getTranslator')
            ->will($this->returnValue($stubTranslator));

        $rule1 = new AvailableForTotalAmountManager($stubAdapter);
        $rule2 = new AvailableForXArticlesManager($stubAdapter);

        $adapter = new CouponBaseAdapter($container);

        $container->set('thelia.constraint.rule.available_for_total_amount', $rule1);
        $container->set('thelia.constraint.rule.available_for_x_articles', $rule2);
        $container->set('thelia.adapter', $adapter);

        return $container;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }
}
