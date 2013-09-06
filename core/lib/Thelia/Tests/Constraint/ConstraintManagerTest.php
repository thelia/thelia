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
use Thelia\Constraint\Rule\AvailableForXArticles;
use Thelia\Constraint\Validator\PriceParam;
use Thelia\Constraint\Validator\RuleValidator;
use Thelia\Constraint\Rule\AvailableForTotalAmount;
use Thelia\Constraint\Rule\Operators;
use Thelia\Coupon\CouponRuleCollection;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Coupon\Type\RemoveXAmount;
use Thelia\Tools\PhpUnitUtils;

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
class ConstraintManagerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    public function setUp()
    {
    }

    public function incompleteTest()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * Check the Rules serialization module
     */
    public function testRuleSerialisation()
    {
        $translator = $this->getMock('\Thelia\Core\Translation\Translator');

        $rule1 = new AvailableForTotalAmount($translator);
        $operators = array(AvailableForTotalAmount::PARAM1_PRICE => Operators::SUPERIOR);
        $values = array(
            AvailableForTotalAmount::PARAM1_PRICE => 40.00,
            AvailableForTotalAmount::PARAM1_CURRENCY => 'EUR'
        );
        $rule1->populateFromForm($operators, $values);

        $rule2 = new AvailableForTotalAmount($translator);
        $operators = array(AvailableForTotalAmount::PARAM1_PRICE => Operators::INFERIOR);
        $values = array(
            AvailableForTotalAmount::PARAM1_PRICE => 400.00,
            AvailableForTotalAmount::PARAM1_CURRENCY => 'EUR'
        );
        $rule2->populateFromForm($operators, $values);

        $rules = new CouponRuleCollection(array($rule1, $rule2));

        /** @var ConstraintManager $constraintManager */
        $constraintManager = new ConstraintManager($this->getContainer());

        $serializedRules = $constraintManager->serializeCouponRuleCollection($rules);
        $unserializedRules = $constraintManager->unserializeCouponRuleCollection($serializedRules);

        $expected = $rules;
        $actual = $unserializedRules;

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

        $translator = $this->getMock('\Thelia\Core\Translation\Translator');
        $rule1 = new AvailableForTotalAmount($translator);
        $rule2 = new AvailableForXArticles($translator);

        $container->set('thelia.constraint.rule.available_for_total_amount', $rule1);
        $container->set('thelia.constraint.rule.available_for_x_articles', $rule2);

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
