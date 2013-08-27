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
    protected function setUp()
    {
    }



    /**
     * Generate valid CouponRuleInterfaces
     *
     * @return array Array of CouponRuleInterface
     */
    public static function generateValidRules()
    {
        $rule1 = new AvailableForTotalAmount(
            array(
                AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                    Operators::SUPERIOR,
                    new PriceParam(
                        , 40.00, 'EUR'
                    )
                )
            )
        );
        $rule2 = new AvailableForTotalAmount(
            array(
                AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                    Operators::INFERIOR,
                    new PriceParam(
                        , 400.00, 'EUR'
                    )
                )
            )
        );
        $rules = new CouponRuleCollection(array($rule1, $rule2));

        return $rules;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    /**
     * Generate a fake Adapter
     *
     * @param array $coupons            Coupons
     * @param float $cartTotalPrice     Cart total price
     * @param float $checkoutTotalPrice Checkout total price
     * @param float $postagePrice       Checkout postage price
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function generateFakeAdapter(array $coupons, $cartTotalPrice, $checkoutTotalPrice, $postagePrice = 6.00)
    {
        $stubCouponBaseAdapter = $this->getMock(
            'Thelia\Coupon\CouponBaseAdapter',
            array(
                'getCurrentCoupons',
                'getCartTotalPrice',
                'getCheckoutTotalPrice',
                'getCheckoutPostagePrice'
            ),
            array()
        );

        $stubCouponBaseAdapter->expects($this->any())
            ->method('getCurrentCoupons')
            ->will($this->returnValue(($coupons)));

        // Return Cart product amount = $cartTotalPrice euros
        $stubCouponBaseAdapter->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue($cartTotalPrice));

        // Return Checkout amount = $checkoutTotalPrice euros
        $stubCouponBaseAdapter->expects($this->any())
            ->method('getCheckoutTotalPrice')
            ->will($this->returnValue($checkoutTotalPrice));

        $stubCouponBaseAdapter->expects($this->any())
            ->method('getCheckoutPostagePrice')
            ->will($this->returnValue($postagePrice));

        return $stubCouponBaseAdapter;
    }
}
