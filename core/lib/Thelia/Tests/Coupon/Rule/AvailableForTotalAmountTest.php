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
use Thelia\Coupon\Parameter\RuleValidator;
use Thelia\Coupon\Rule\AvailableForTotalAmount;
use Thelia\Coupon\Rule\Operators;
use Thelia\Exception\InvalidRuleOperatorException;
use Thelia\Exception\InvalidRuleValueException;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test AvailableForTotalAmount Class
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class AvailableForTotalAmountTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
    }

    protected function generateValidCouponBaseAdapterMock()
    {
        /** @var CouponAdapterInterface $stubTheliaAdapter */
       $stubTheliaAdapter = $this->getMock(
           'CouponBaseAdapter',
           array('getCheckoutTotalPrice'),
           array()
       );
        $stubTheliaAdapter->expects($this->any())
            ->method('getCheckoutTotalPrice')
            ->will($this->returnValue(421.23));

        return $stubTheliaAdapter;
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\AvailableForTotalAmount::checkBackOfficeInput
     *
     */
    public function testValidBackOfficeInput()
    {
        /** @var CouponAdapterInterface $stubTheliaAdapter */
        $stubTheliaAdapter = $this->generateValidCouponBaseAdapterMock();

        $validators = array(
            AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                Operators::SUPERIOR,
                new PriceParam(
                    421.23,
                    'EUR'
                )
            )
        );
        $validated = array(
            AvailableForTotalAmount::PARAM1_PRICE => $stubTheliaAdapter->getCheckoutTotalPrice()
        );
        $rule = new AvailableForTotalAmount($validators, $validated);

        $expected = true;
        $actual = $rule->checkBackOfficeInput();
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\AvailableForTotalAmount::checkBackOfficeInput
     * @expectedException \Thelia\Exception\InvalidRuleOperatorException
     *
     */
    public function testInValidBackOfficeInputOperator()
    {
        /** @var CouponAdapterInterface $stubTheliaAdapter */
        $stubTheliaAdapter = $this->generateValidCouponBaseAdapterMock();

        $validators = array(
            AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                'X',
                new PriceParam(
                    421.23,
                    'EUR'
                )
            )
        );

        $validated = array(
            AvailableForTotalAmount::PARAM1_PRICE => $stubTheliaAdapter->getCheckoutTotalPrice()
        );
        $rule = new AvailableForTotalAmount($validators, $validated);

        $expected = false;
        $actual = $rule->checkBackOfficeInput();
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\AvailableForTotalAmount::checkBackOfficeInput
     * @expectedException ErrorException
     *
     */
    public function testInValidBackOfficeInputValue()
    {
        /** @var CouponAdapterInterface $stubTheliaAdapter */
        $stubTheliaAdapter = $this->generateValidCouponBaseAdapterMock();

        $validators = array(
            AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                Operators::SUPERIOR,
                421
            )
        );

        $validated = array(
            AvailableForTotalAmount::PARAM1_PRICE => $stubTheliaAdapter->getCheckoutTotalPrice()
        );
        $rule = new AvailableForTotalAmount($validators, $validated);

        $expected = false;
        $actual = $rule->checkBackOfficeInput();
        $this->assertEquals($expected, $actual);
    }



    /**
     *
     * @covers Thelia\Coupon\Rule\AvailableForTotalAmount::checkCheckoutInput
     *
     */
    public function testValidCheckoutInput()
    {
        /** @var CouponAdapterInterface $stubTheliaAdapter */
        $stubTheliaAdapter = $this->generateValidCouponBaseAdapterMock();

        $validators = array(
            AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                Operators::SUPERIOR,
                new PriceParam(
                    421.23,
                    'EUR'
                )
            )
        );

        $validated = array(
            AvailableForTotalAmount::PARAM1_PRICE => $stubTheliaAdapter->getCheckoutTotalPrice()
        );
        $rule = new AvailableForTotalAmount($validators, $validated);

        $expected = true;
        $actual = $rule->checkCheckoutInput();
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\AvailableForTotalAmount::checkCheckoutInput
     * @expectedException \Thelia\Exception\InvalidRuleValueException
     *
     */
    public function testInValidCheckoutInputValue()
    {
        $validators = array(
            AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                Operators::SUPERIOR,
                new PriceParam(
                    421.23,
                    'EUR'
                )
            )
        );

        $validated = array(
            AvailableForTotalAmount::PARAM1_PRICE => 421
        );
        $rule = new AvailableForTotalAmount($validators, $validated);

        $expected = false;
        $actual = $rule->checkCheckoutInput();
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\AvailableForTotalAmount::checkCheckoutInput
     * @expectedException \Thelia\Exception\InvalidRuleValueException
     *
     */
    public function testInValidCheckoutInputType()
    {
        $validators = array(
            AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                Operators::SUPERIOR,
                new PriceParam(
                    421.23,
                    'EUR'
                )
            )
        );

        $validated = array(
            AvailableForTotalAmount::PARAM1_PRICE => 421
        );
        $rule = new AvailableForTotalAmount($validators, $validated);

        $expected = false;
        $actual = $rule->checkCheckoutInput();
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\AvailableForTotalAmount::isMatching
     *
     */
    public function testMatchingRuleInferior()
    {
        $validators = array(
            AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                Operators::INFERIOR,
                new PriceParam(
                    421.23,
                    'EUR'
                )
            )
        );

        $validated = array(
            AvailableForTotalAmount::PARAM1_PRICE => 421.22
        );
        $rule = new AvailableForTotalAmount($validators, $validated);

        $expected = true;
        $actual = $rule->isMatching();
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\AvailableForTotalAmount::isMatching
     *
     */
    public function testNotMatchingRuleInferior()
    {
        $validators = array(
            AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                Operators::INFERIOR,
                new PriceParam(
                    421.23,
                    'EUR'
                )
            )
        );

        $validated = array(
            AvailableForTotalAmount::PARAM1_PRICE => 421.23
        );
        $rule = new AvailableForTotalAmount($validators, $validated);

        $expected = false;
        $actual = $rule->isMatching();
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\AvailableForTotalAmount::isMatching
     *
     */
    public function testMatchingRuleEqual()
    {
        /** @var CouponAdapterInterface $stubTheliaAdapter */
        $stubTheliaAdapter = $this->generateValidCouponBaseAdapterMock();

        $validators = array(
            AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                Operators::EQUAL,
                new PriceParam(
                    421.23,
                    'EUR'
                )
            )
        );

        $validated = array(
            AvailableForTotalAmount::PARAM1_PRICE => $stubTheliaAdapter->getCheckoutTotalPrice()
        );
        $rule = new AvailableForTotalAmount($validators, $validated);

        $expected = true;
        $actual = $rule->isMatching();
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\AvailableForTotalAmount::isMatching
     *
     */
    public function testNotMatchingRuleEqual()
    {
        $validators = array(
            AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                Operators::EQUAL,
                new PriceParam(
                    421.23,
                    'EUR'
                )
            )
        );

        $validated = array(
            AvailableForTotalAmount::PARAM1_PRICE => 421.22
        );
        $rule = new AvailableForTotalAmount($validators, $validated);

        $expected = false;
        $actual = $rule->isMatching();
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\AvailableForTotalAmount::isMatching
     *
     */
    public function testMatchingRuleSuperior()
    {
        $validators = array(
            AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                Operators::SUPERIOR,
                new PriceParam(
                    421.23,
                    'EUR'
                )
            )
        );

        $validated = array(
            AvailableForTotalAmount::PARAM1_PRICE => 421.24
        );
        $rule = new AvailableForTotalAmount($validators, $validated);

        $expected = true;
        $actual = $rule->isMatching();
        $this->assertEquals($expected, $actual);
    }

    /**
     *
     * @covers Thelia\Coupon\Rule\AvailableForTotalAmount::isMatching
     *
     */
    public function testNotMatchingRuleSuperior()
    {
        $validators = array(
            AvailableForTotalAmount::PARAM1_PRICE => new RuleValidator(
                Operators::SUPERIOR,
                new PriceParam(
                    421.23,
                    'EUR'
                )
            )
        );

        $validated = array(
            AvailableForTotalAmount::PARAM1_PRICE => 421.23
        );
        $rule = new AvailableForTotalAmount($validators, $validated);

        $expected = false;
        $actual = $rule->isMatching();
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
