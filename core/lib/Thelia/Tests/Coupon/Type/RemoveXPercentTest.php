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

use PHPUnit_Framework_TestCase;
use Thelia\Constraint\Rule\AvailableForTotalAmountManager;
use Thelia\Constraint\Rule\Operators;
use Thelia\Constraint\Validator\PriceParam;
use Thelia\Constraint\Validator\RuleValidator;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Coupon\Type\RemoveXPercentManager;

//require_once '../CouponManagerTest.php';

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test RemoveXPercent Class
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class RemoveXPercentTest extends \PHPUnit_Framework_TestCase
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
//    }
//
//    /**
//     * Test if a Coupon can be Cumulative
//     *
//     * @covers Thelia\Coupon\type\RemoveXPercentManager::isCumulative
//     *
//     */
//    public function testIsCumulative()
//    {
//        $coupon = $this->generateValidCoupon(null, null, null, null, null, null, null, null, true, true);
//
//        $actual = $coupon->isCumulative();
//        $this->assertTrue($actual);
//    }
//
//    /**
//     *  Test if a Coupon can be non cumulative
//     *
//     * @covers Thelia\Coupon\type\RemoveXPercentManager::isCumulative
//     *
//     */
//    public function testIsNotCumulative()
//    {
//        $coupon = $this->generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        $actual = $coupon->isCumulative();
//        $this->assertFalse($actual);
//    }
//
//
//    /**
//     *  Test if a Coupon can remove postage
//     *
//     * @covers Thelia\Coupon\type\RemoveXPercentManager::isRemovingPostage
//     *
//     */
//    public function testIsRemovingPostage()
//    {
//        $coupon = $this->generateValidCoupon(null, null, null, null, null, null, null, null, true, true);
//
//        $actual = $coupon->isRemovingPostage();
//        $this->assertTrue($actual);
//    }
//
//    /**
//     * Test if a Coupon won't remove postage if not set to
//     *
//     * @covers Thelia\Coupon\type\RemoveXPercentManager::isRemovingPostage
//     */
//    public function testIsNotRemovingPostage()
//    {
//        $coupon = $this->generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        $actual = $coupon->isRemovingPostage();
//        $this->assertFalse($actual);
//    }
//
//
//    /**
//     * Test if a Coupon has the effect expected (discount 10euros)
//     *
//     * @covers Thelia\Coupon\type\RemoveXPercentManager::getEffect
//     */
//    public function testGetEffect()
//    {
//        $adapter = $this->generateFakeAdapter(245);
//        $coupon = $this->generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        $expected = 24.50;
//        $actual = $coupon->getDiscount($adapter);
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Test Coupon rule setter
//     *
//     * @covers Thelia\Coupon\type\RemoveXPercentManager::setRules
//     * @covers Thelia\Coupon\type\RemoveXPercentManager::getRules
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
//        $coupon = $this->generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        // When
//        $coupon->setRules(new CouponRuleCollection(array($rule0, $rule1, $rule2)));
//
//        // Then
//        $expected = 3;
//        $this->assertCount($expected, $coupon->getRules()->getRules());
//    }
//
//    /**
//     * Test Coupon rule setter
//     *
//     * @covers Thelia\Coupon\type\RemoveXPercentManager::setRules
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
//        $coupon = $this->generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        // When
//        $coupon->setRules(new CouponRuleCollection(array($rule0, $rule1, $rule2)));
//    }
//
//    /**
//     * Test Coupon effect for rule Total Amount < 400
//     *
//     * @covers Thelia\Coupon\type\RemoveXPercentManager::getEffect
//     *
//     */
//    public function testGetEffectIfTotalAmountInferiorTo400Valid()
//    {
//        // Given
//        $rule0 = $this->generateValidRuleAvailableForTotalAmountOperatorTo(
//            Operators::INFERIOR,
//            400.00
//        );
//        $coupon = $this->generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        // When
//        $coupon->setRules(new CouponRuleCollection(array($rule0)));
//
//        // Then
//        $expected = 24.50;
//        $actual = $coupon->getDiscount();
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Test Coupon effect for rule Total Amount <= 400
//     *
//     * @covers Thelia\Coupon\type\RemoveXPercentManager::getEffect
//     *
//     */
//    public function testGetEffectIfTotalAmountInferiorOrEqualTo400Valid()
//    {
//        // Given
//        $rule0 = $this->generateValidRuleAvailableForTotalAmountOperatorTo(
//            Operators::INFERIOR_OR_EQUAL,
//            400.00
//        );
//        $coupon = $this->generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        // When
//        $coupon->setRules(new CouponRuleCollection(array($rule0)));
//
//        // Then
//        $expected = 24.50;
//        $actual = $coupon->getDiscount();
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Test Coupon effect for rule Total Amount == 400
//     *
//     * @covers Thelia\Coupon\type\RemoveXPercentManager::getEffect
//     *
//     */
//    public function testGetEffectIfTotalAmountEqualTo400Valid()
//    {
//        // Given
//        $rule0 = $this->generateValidRuleAvailableForTotalAmountOperatorTo(
//            Operators::EQUAL,
//            400.00
//        );
//        $coupon = $this->generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        // When
//        $coupon->setRules(new CouponRuleCollection(array($rule0)));
//
//        // Then
//        $expected = 24.50;
//        $actual = $coupon->getDiscount();
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Test Coupon effect for rule Total Amount >= 400
//     *
//     * @covers Thelia\Coupon\type\RemoveXPercentManager::getEffect
//     *
//     */
//    public function testGetEffectIfTotalAmountSuperiorOrEqualTo400Valid()
//    {
//        // Given
//        $rule0 = $this->generateValidRuleAvailableForTotalAmountOperatorTo(
//            Operators::SUPERIOR_OR_EQUAL,
//            400.00
//        );
//        $coupon = $this->generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        // When
//        $coupon->setRules(new CouponRuleCollection(array($rule0)));
//
//        // Then
//        $expected = 24.50;
//        $actual = $coupon->getDiscount();
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Test Coupon effect for rule Total Amount > 400
//     *
//     * @covers Thelia\Coupon\type\RemoveXPercentManager::getEffect
//     *
//     */
//    public function testGetEffectIfTotalAmountSuperiorTo400Valid()
//    {
//        // Given
//        $rule0 = $this->generateValidRuleAvailableForTotalAmountOperatorTo(
//            Operators::SUPERIOR,
//            400.00
//        );
//        $coupon = $this->generateValidCoupon(null, null, null, null, null, null, null, null, false, false);
//
//        // When
//        $coupon->setRules(new CouponRuleCollection(array($rule0)));
//
//        // Then
//        $expected = 24.50;
//        $actual = $coupon->getDiscount();
//        $this->assertEquals($expected, $actual);
//    }
//
//    /**
//     * Generate valid CouponInterface
//     *
//     * @param string               $code                       Coupon Code
//     * @param string               $title                      Coupon Title
//     * @param string               $shortDescription           Coupon short
//     *                                                         description
//     * @param string               $description                Coupon description
//     * @param float                $amount                     Coupon discount
//     * @param bool                 $isEnabled                  Is Coupon enabled
//     * @param \DateTime            $expirationDate             Coupon expiration date
//     * @param CouponRuleCollection $rules                      Coupon rules
//     * @param bool                 $isCumulative               If is cumulative
//     * @param bool                 $isRemovingPostage          If is removing postage
//     * @param bool                 $isAvailableOnSpecialOffers If is available on
//     *                                                         special offers or not
//     * @param int                  $maxUsage                   How many time a Coupon
//     *                                                         can be used
//     *
//     * @return CouponInterface
//     */
//    public function generateValidCoupon(
//        $code = null,
//        $title = null,
//        $shortDescription = null,
//        $description = null,
//        $percent = null,
//        $isEnabled = null,
//        $expirationDate = null,
//        $rules = null,
//        $isCumulative = null,
//        $isRemovingPostage = null,
//        $isAvailableOnSpecialOffers = null,
//        $maxUsage = null
//    ) {
//        $adapter = $this->generateFakeAdapter(245);
//
//        if ($code === null) {
//            $code = CouponManagerTest::VALID_CODE;
//        }
//        if ($title === null) {
//            $title = CouponManagerTest::VALID_TITLE;
//        }
//        if ($shortDescription === null) {
//            $shortDescription = CouponManagerTest::VALID_SHORT_DESCRIPTION;
//        }
//        if ($description === null) {
//            $description = CouponManagerTest::VALID_DESCRIPTION;
//        }
//        if ($percent === null) {
//            $percent = 10.00;
//        }
//        if ($isEnabled === null) {
//            $isEnabled = true;
//        }
//        if ($isCumulative === null) {
//            $isCumulative = true;
//        }
//        if ($isRemovingPostage === null) {
//            $isRemovingPostage = false;
//        }
//        if ($isAvailableOnSpecialOffers === null) {
//            $isAvailableOnSpecialOffers = true;
//        }
//        if ($maxUsage === null) {
//            $maxUsage = 40;
//        }
//
//        if ($expirationDate === null) {
//            $expirationDate = new \DateTime();
//            $expirationDate->setTimestamp(strtotime("today + 2 months"));
//        }
//
//        $coupon = new RemoveXPercent($adapter, $code, $title, $shortDescription, $description, $percent, $isCumulative, $isRemovingPostage, $isAvailableOnSpecialOffers, $isEnabled, $maxUsage, $expirationDate);
//
//        if ($rules === null) {
//            $rules = CouponManagerTest::generateValidRules();
//        }
//
//        $coupon->setRules($rules);
//
//        return $coupon;
//    }
//
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
//        $adapter = new CouponBaseAdapter();
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
//
//    /**
//     * Generate a fake Adapter
//     *
//     * @param float $cartTotalPrice     Cart total price
//     *
//     * @return \PHPUnit_Framework_MockObject_MockObject
//     */
//    public function generateFakeAdapter($cartTotalPrice)
//    {
//        $stubCouponBaseAdapter = $this->getMock(
//            'Thelia\Coupon\CouponBaseAdapter',
//            array(
//                'getCartTotalPrice'
//            ),
//            array()
//        );
//
//        $stubCouponBaseAdapter->expects($this->any())
//            ->method('getCartTotalPrice')
//            ->will($this->returnValue(($cartTotalPrice)));
//
//        return $stubCouponBaseAdapter;
//    }
//
//    /**
//     * Tears down the fixture, for example, closes a network connection.
//     * This method is called after a test is executed.
//     */
//    protected function tearDown()
//    {
//    }

}
