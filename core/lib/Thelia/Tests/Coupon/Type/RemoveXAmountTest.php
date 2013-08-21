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

use Thelia\Coupon\Parameter\PriceParam;
use Thelia\Coupon\Rule\AvailableForTotalAmount;
use Thelia\Coupon\Rule\Operators;
use Thelia\Coupon\Type\RemoveXAmount;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Thrown when a Rule receive an invalid Parameter
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class RemoveXAmountTest extends \PHPUnit_Framework_TestCase
{

    CONST VALID_COUPON_CODE = 'XMAS';
    CONST VALID_COUPON_TITLE = 'XMAS Coupon';
    CONST VALID_COUPON_SHORT_DESCRIPTION = 'Coupon for christmas';
    CONST VALID_COUPON_DESCRIPTION = '<h1>Lorem</h1><span>ipsum</span>';
    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {

    }


    protected function generateValidCumulativeRemovingPostageCoupon()
    {
        $coupon = new RemoveXAmount(
            self::VALID_COUPON_CODE,
            self::VALID_COUPON_TITLE,
            self::VALID_COUPON_SHORT_DESCRIPTION,
            self::VALID_COUPON_DESCRIPTION,
            30.00,
            true,
            true
        );

        return $coupon;
    }

    protected function generateValidNonCumulativeNonRemovingPostageCoupon()
    {
        $coupon = new RemoveXAmount(
            self::VALID_COUPON_CODE,
            self::VALID_COUPON_TITLE,
            self::VALID_COUPON_SHORT_DESCRIPTION,
            self::VALID_COUPON_DESCRIPTION,
            30.00,
            false,
            false
        );

        return $coupon;
    }

    /**
     *
     * @covers Thelia\Coupon\type\RemoveXAmount::getCode
     * @covers Thelia\Coupon\type\RemoveXAmount::getTitle
     * @covers Thelia\Coupon\type\RemoveXAmount::getShortDescription
     * @covers Thelia\Coupon\type\RemoveXAmount::getDescription
     *
     */
    public function testDisplay()
    {

        $coupon = $this->generateValidCumulativeRemovingPostageCoupon();

        $expected = self::VALID_COUPON_CODE;
        $actual = $coupon->getCode();
        $this->assertEquals($expected, $actual);

        $expected = self::VALID_COUPON_TITLE;
        $actual = $coupon->getTitle();
        $this->assertEquals($expected, $actual);

        $expected = self::VALID_COUPON_SHORT_DESCRIPTION;
        $actual = $coupon->getShortDescription();
        $this->assertEquals($expected, $actual);

        $expected = self::VALID_COUPON_DESCRIPTION;
        $actual = $coupon->getDescription();
        $this->assertEquals($expected, $actual);

    }


    /**
     *
     * @covers Thelia\Coupon\type\RemoveXAmount::isCumulative
     *
     */
    public function testIsCumulative()
    {

        $coupon = $this->generateValidCumulativeRemovingPostageCoupon();

        $actual = $coupon->isCumulative();
        $this->assertTrue($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\type\RemoveXAmount::isCumulative
     *
     */
    public function testIsNotCumulative()
    {

        $coupon = $this->generateValidNonCumulativeNonRemovingPostageCoupon();

        $actual = $coupon->isCumulative();
        $this->assertFalse($actual);
    }


    /**
     *
     * @covers Thelia\Coupon\type\RemoveXAmount::isRemovingPostage
     *
     */
    public function testIsRemovingPostage()
    {

        $coupon = $this->generateValidCumulativeRemovingPostageCoupon();

        $actual = $coupon->isRemovingPostage();
        $this->assertTrue($actual);
    }

    /**
     *
     * @covers Thelia\Coupon\type\RemoveXAmount::isRemovingPostage
     *
     */
    public function testIsNotRemovingPostage()
    {

        $coupon = $this->generateValidNonCumulativeNonRemovingPostageCoupon();

        $actual = $coupon->isRemovingPostage();
        $this->assertFalse($actual);
    }


    /**
     *
     * @covers Thelia\Coupon\type\RemoveXAmount::getEffect
     *
     */
    public function testGetEffect()
    {

        $coupon = $this->generateValidNonCumulativeNonRemovingPostageCoupon();

        $expected = -30.00;
        $actual = $coupon->getEffect();
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\type\RemoveXAmount::addRule
     * @covers Thelia\Coupon\type\RemoveXAmount::getRules
     *
     */
    public function testAddRuleValid()
    {
        // Given
        $rule1 = $this->generateValideRuleAvailableForTotalAmountOperatorTo(
            Operators::INFERIOR,
            100.23
        );
        $rule2 = $this->generateValideRuleAvailableForTotalAmountOperatorTo(
            Operators::SUPERIOR,
            421.23
        );

        $coupon = $this->generateValidNonCumulativeNonRemovingPostageCoupon();

        // When
        $coupon->addRule($rule1)
            ->addRule($rule2);

        // Then
        $expected = 2;
        $this->assertCount($expected, $coupon->getRules());
    }


    /**
     *
     * @covers Thelia\Coupon\type\RemoveXAmount::setRules
     * @covers Thelia\Coupon\type\RemoveXAmount::getRules
     *
     */
    public function testSetRulesValid()
    {
        // Given
        $rule0 = $this->generateValideRuleAvailableForTotalAmountOperatorTo(
            Operators::EQUAL,
            20.00
        );
        $rule1 = $this->generateValideRuleAvailableForTotalAmountOperatorTo(
            Operators::INFERIOR,
            100.23
        );
        $rule2 = $this->generateValideRuleAvailableForTotalAmountOperatorTo(
            Operators::SUPERIOR,
            421.23
        );

        $coupon = $this->generateValidNonCumulativeNonRemovingPostageCoupon();

        // When
        $coupon->addRule($rule0)
            ->setRules(array($rule1, $rule2));


        // Then
        $expected = 2;
        $this->assertCount($expected, $coupon->getRules());
    }

    /**
     *
     * @covers Thelia\Coupon\type\RemoveXAmount::setRules
     * @expectedException \Thelia\Exception\InvalidRuleException
     *
     */
    public function testSetRulesInvalid()
    {
        // Given
        $rule0 = $this->generateValideRuleAvailableForTotalAmountOperatorTo(
            Operators::EQUAL,
            20.00
        );
        $rule1 = $this->generateValideRuleAvailableForTotalAmountOperatorTo(
            Operators::INFERIOR,
            100.23
        );
        $rule2 = $this;

        $coupon = $this->generateValidNonCumulativeNonRemovingPostageCoupon();

        // When
        $coupon->addRule($rule0)
            ->setRules(array($rule1, $rule2));
    }

    /**
     *
     * @covers Thelia\Coupon\type\RemoveXAmount::getEffect
     *
     */
    public function testGetEffectIfTotalAmountInferiorTo400Valid()
    {
        // Given
        $rule0 = $this->generateValideRuleAvailableForTotalAmountOperatorTo(
            Operators::INFERIOR,
            400.00
        );
        $coupon = $this->generateValidNonCumulativeNonRemovingPostageCoupon();

        // When
        $coupon->addRule($rule0);

        // Then
        $expected = -30.00;
        $actual = $coupon->getEffect();
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\type\RemoveXAmount::getEffect
     *
     */
    public function testGetEffectIfTotalAmountInferiorOrEqualTo400Valid()
    {
        // Given
        $rule0 = $this->generateValideRuleAvailableForTotalAmountOperatorTo(
            Operators::INFERIOR_OR_EQUAL,
            400.00
        );
        $coupon = $this->generateValidNonCumulativeNonRemovingPostageCoupon();

        // When
        $coupon->addRule($rule0);

        // Then
        $expected = -30.00;
        $actual = $coupon->getEffect();
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\type\RemoveXAmount::getEffect
     *
     */
    public function testGetEffectIfTotalAmountEqualTo400Valid()
    {
        // Given
        $rule0 = $this->generateValideRuleAvailableForTotalAmountOperatorTo(
            Operators::EQUAL,
            400.00
        );
        $coupon = $this->generateValidNonCumulativeNonRemovingPostageCoupon();

        // When
        $coupon->addRule($rule0);

        // Then
        $expected = -30.00;
        $actual = $coupon->getEffect();
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\type\RemoveXAmount::getEffect
     *
     */
    public function testGetEffectIfTotalAmountSuperiorOrEqualTo400Valid()
    {
        // Given
        $rule0 = $this->generateValideRuleAvailableForTotalAmountOperatorTo(
            Operators::SUPERIOR_OR_EQUAL,
            400.00
        );
        $coupon = $this->generateValidNonCumulativeNonRemovingPostageCoupon();

        // When
        $coupon->addRule($rule0);

        // Then
        $expected = -30.00;
        $actual = $coupon->getEffect();
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\type\RemoveXAmount::getEffect
     *
     */
    public function testGetEffectIfTotalAmountSuperiorTo400Valid()
    {
        // Given
        $rule0 = $this->generateValideRuleAvailableForTotalAmountOperatorTo(
            Operators::SUPERIOR,
            400.00
        );
        $coupon = $this->generateValidNonCumulativeNonRemovingPostageCoupon();

        // When
        $coupon->addRule($rule0);

        // Then
        $expected = -30.00;
        $actual = $coupon->getEffect();
        $this->assertEquals($expected, $actual);
    }



    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    /**
     * Generate valid rule AvailableForTotalAmount
     * according to given operator and amount
     *
     * @param string $operator Operators::CONST
     * @param float  $amount   Amount with 2 decimals
     *
     * @return AvailableForTotalAmount
     */
    protected function generateValideRuleAvailableForTotalAmountOperatorTo($operator, $amount)
    {
        $validators = array(
            AvailableForTotalAmount::PARAM1_PRICE => array(
                AvailableForTotalAmount::OPERATOR => $operator,
                AvailableForTotalAmount::VALUE => new PriceParam($amount, 'EUR')
            )
        );

        return new AvailableForTotalAmount($validators);
    }

}
