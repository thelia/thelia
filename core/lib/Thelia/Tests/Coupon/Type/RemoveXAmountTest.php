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
use Thelia\Constraint\Rule\Operators;
use Thelia\Coupon\Type\RemoveXAmountManager;

//require_once '../CouponManagerTest.php';

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test RemoveXAmount Class
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class RemoveXAmountTest extends \PHPUnit_Framework_TestCase
{
    public function testSomething()
    {
        // Stop here and mark this test as incomplete.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
//    /**
//     * Sets up the fixture, for example, opens a network connection.
//     * This method is called before a test is executed.
//     */
//    protected function setUp()
//    {
//
//    }
//
//    /**
//     * Test if a Coupon is well displayed
//     *
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::getCode
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::getTitle
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::getShortDescription
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::getDescription
//     *
//     */
//    public function testDisplay()
//    {
//        $coupon = CouponManagerTest::generateValidCoupon(null, null, null, null, null, null, null, null, true, true);
//
//        $expected = CouponManagerTest::VALID_CODE;
//        $actual = $coupon->getCode();
//        $this->assertEquals($expected, $actual);
//
//        $expected = CouponManagerTest::VALID_TITLE;
//        $actual = $coupon->getTitle();
//        $this->assertEquals($expected, $actual);
//
//        $expected = CouponManagerTest::VALID_SHORT_DESCRIPTION;
//        $actual = $coupon->getShortDescription();
//        $this->assertEquals($expected, $actual);
//
//        $expected = CouponManagerTest::VALID_DESCRIPTION;
//        $actual = $coupon->getDescription();
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Test if a Coupon can be Cumulative
//     *
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::isCumulative
//     *
//     */
//    public function testIsCumulative()
//    {
//        $coupon = CouponManagerTest::generateValidCoupon(null, null, null, null, null, null, null, null, true, true);
//
//        $actual = $coupon->isCumulative();
//        $this->assertTrue($actual);
//    }
//
//    /**
//     *  Test if a Coupon can be non cumulative
//     *
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::isCumulative
//     *
//     */
//    public function testIsNotCumulative()
//    {
//        $coupon = CouponManagerTest::generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        $actual = $coupon->isCumulative();
//        $this->assertFalse($actual);
//    }
//
//
//    /**
//     *  Test if a Coupon can remove postage
//     *
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::isRemovingPostage
//     *
//     */
//    public function testIsRemovingPostage()
//    {
//        $coupon = CouponManagerTest::generateValidCoupon(null, null, null, null, null, null, null, null, true, true);
//
//        $actual = $coupon->isRemovingPostage();
//        $this->assertTrue($actual);
//    }
//
//    /**
//     * Test if a Coupon won't remove postage if not set to
//     *
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::isRemovingPostage
//     */
//    public function testIsNotRemovingPostage()
//    {
//        $coupon = CouponManagerTest::generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        $actual = $coupon->isRemovingPostage();
//        $this->assertFalse($actual);
//    }
//
//
//    /**
//     * Test if a Coupon has the effect expected (discount 10euros)
//     *
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::getEffect
//     */
//    public function testGetEffect()
//    {
//        $adapter = new BaseAdapter();
//        $coupon = CouponManagerTest::generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        $expected = 10;
//        $actual = $coupon->getDiscount();
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Test Coupon rule setter
//     *
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::setRules
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::getRules
//     */
//    public function testSetRulesValid()
//    {
//        // Given
//        $rule0 = $this->generateValidRuleAvailableForTotalAmountOperatorTo(
//            Operators::EQUAL,
//            20.00
//        );
//        $rule1 = $this->generateValidRuleAvailableForTotalAmountOperatorTo(
//            Operators::INFERIOR,
//            100.23
//        );
//        $rule2 = $this->generateValidRuleAvailableForTotalAmountOperatorTo(
//            Operators::SUPERIOR,
//            421.23
//        );
//
//        $coupon = CouponManagerTest::generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        // When
//        $coupon->setRules(new ConditionCollection(array($rule0, $rule1, $rule2)));
//
//        // Then
//        $expected = 3;
//        $this->assertCount($expected, $coupon->getRules()->getRules());
//    }
//
//    /**
//     * Test Coupon rule setter
//     *
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::setRules
//     * @expectedException \Thelia\Exception\InvalidRuleException
//     *
//     */
//    public function testSetRulesInvalid()
//    {
//        // Given
//        $rule0 = $this->generateValidRuleAvailableForTotalAmountOperatorTo(
//            Operators::EQUAL,
//            20.00
//        );
//        $rule1 = $this->generateValidRuleAvailableForTotalAmountOperatorTo(
//            Operators::INFERIOR,
//            100.23
//        );
//        $rule2 = $this;
//
//        $coupon = CouponManagerTest::generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        // When
//        $coupon->setRules(new ConditionCollection(array($rule0, $rule1, $rule2)));
//    }
//
//    /**
//     * Test Coupon effect for rule Total Amount < 400
//     *
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::getEffect
//     *
//     */
//    public function testGetEffectIfTotalAmountInferiorTo400Valid()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $rule0 = $this->generateValidRuleAvailableForTotalAmountOperatorTo(
//            Operators::INFERIOR,
//            400.00
//        );
//        $coupon = CouponManagerTest::generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        // When
//        $coupon->setRules(new ConditionCollection(array($rule0)));
//
//        // Then
//        $expected = 10.00;
//        $actual = $coupon->getDiscount();
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Test Coupon effect for rule Total Amount <= 400
//     *
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::getEffect
//     *
//     */
//    public function testGetEffectIfTotalAmountInferiorOrEqualTo400Valid()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $rule0 = $this->generateValidRuleAvailableForTotalAmountOperatorTo(
//            Operators::INFERIOR_OR_EQUAL,
//            400.00
//        );
//        $coupon = CouponManagerTest::generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        // When
//        $coupon->setRules(new ConditionCollection(array($rule0)));
//
//        // Then
//        $expected = 10.00;
//        $actual = $coupon->getDiscount();
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Test Coupon effect for rule Total Amount == 400
//     *
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::getEffect
//     *
//     */
//    public function testGetEffectIfTotalAmountEqualTo400Valid()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $rule0 = $this->generateValidRuleAvailableForTotalAmountOperatorTo(
//            Operators::EQUAL,
//            400.00
//        );
//        $coupon = CouponManagerTest::generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        // When
//        $coupon->setRules(new ConditionCollection(array($rule0)));
//
//        // Then
//        $expected = 10.00;
//        $actual = $coupon->getDiscount();
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Test Coupon effect for rule Total Amount >= 400
//     *
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::getEffect
//     *
//     */
//    public function testGetEffectIfTotalAmountSuperiorOrEqualTo400Valid()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $rule0 = $this->generateValidRuleAvailableForTotalAmountOperatorTo(
//            Operators::SUPERIOR_OR_EQUAL,
//            400.00
//        );
//        $coupon = CouponManagerTest::generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        // When
//        $coupon->setRules(new ConditionCollection(array($rule0)));
//
//        // Then
//        $expected = 10.00;
//        $actual = $coupon->getDiscount();
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Test Coupon effect for rule Total Amount > 400
//     *
//     * @covers Thelia\Coupon\type\RemoveXAmountManager::getEffect
//     *
//     */
//    public function testGetEffectIfTotalAmountSuperiorTo400Valid()
//    {
//        // Given
//        $adapter = new BaseAdapter();
//        $rule0 = $this->generateValidRuleAvailableForTotalAmountOperatorTo(
//            Operators::SUPERIOR,
//            400.00
//        );
//        $coupon = CouponManagerTest::generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        // When
//        $coupon->setRules(new ConditionCollection(array($rule0)));
//
//        // Then
//        $expected = 10.00;
//        $actual = $coupon->getDiscount();
//        $this->assertEquals($expected, $actual);
//    }
//
//
//
//    /**
//     * Tears down the fixture, for example, closes a network connection.
//     * This method is called after a test is executed.
//     */
//    protected function tearDown()
//    {
//    }
//
//    /**
//     * Generate valid rule AvailableForTotalAmount
//     * according to given operator and amount
//     *
//     * @param string $operator Operators::CONST
//     * @param float  $amount   Amount with 2 decimals
//     *
//     * @return AvailableForTotalAmount
//     */
//    protected function generateValidRuleAvailableForTotalAmountOperatorTo($operator, $amount)
//    {
//        $adapter = new BaseAdapter();
//        $validators = array(
//            AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
//                $operator,
//                new PriceParam(
//                    $adapter,
//                    $amount,
//                    'EUR'
//                )
//            )
//        );
//
//        return new AvailableForTotalAmount($adapter, $validators);
//    }

}
