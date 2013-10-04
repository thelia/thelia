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

use Thelia\Constraint\Validator\PriceParam;
use Thelia\Constraint\Validator\RuleValidator;
use Thelia\Constraint\Rule\AvailableForTotalAmount;
use Thelia\Constraint\Rule\Operators;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test ConditionCollection Class
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponRuleCollectionTest extends \PHPUnit_Framework_TestCase
{
    public function testSomething()
    {
        // Stop here and mark this test as incomplete.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
//    /**
//     *
//     */
//    public function testRuleSerialisation()
//    {
////        $rule1 = new AvailableForTotalAmount(
////            , array(
////                AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
////                    Operators::SUPERIOR,
////                    new PriceParam(
////                        , 40.00, 'EUR'
////                    )
////                )
////            )
////        );
////        $rule2 = new AvailableForTotalAmount(
////            , array(
////                AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
////                    Operators::INFERIOR,
////                    new PriceParam(
////                        , 400.00, 'EUR'
////                    )
////                )
////            )
////        );
////        $rules = new ConditionCollection(array($rule1, $rule2));
////
////        $serializedRules = base64_encode(serialize($rules));
////        $unserializedRules = unserialize(base64_decode($serializedRules));
////
////        $expected = $rules;
////        $actual = $unserializedRules;
////
////        $this->assertEquals($expected, $actual);
//    }
}
