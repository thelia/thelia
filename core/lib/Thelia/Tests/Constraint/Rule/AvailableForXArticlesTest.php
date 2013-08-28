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

use Thelia\Constraint\Rule\AvailableForXArticles;
use Thelia\Constraint\Rule\Operators;
use Thelia\Constraint\Validator\QuantityParam;
use Thelia\Constraint\Validator\RuleValidator;
use Thelia\Exception\InvalidRuleOperatorException;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test AvailableForXArticles Class
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class AvailableForXArticlesTest extends \PHPUnit_Framework_TestCase
{

    /** @var CouponAdapterInterface $stubTheliaAdapter */
    protected $stubTheliaAdapter = null;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        /** @var CouponAdapterInterface $stubTheliaAdapter */
        $this->stubTheliaAdapter = $this->generateValidCouponBaseAdapterMock();
    }

    /**
     * Generate valid CouponBaseAdapter
     *
     * @param int $nbArticlesInCart Total articles in the current Cart
     *
     * @return CouponAdapterInterface
     */
    protected function generateValidCouponBaseAdapterMock($nbArticlesInCart = 4)
    {
        /** @var CouponAdapterInterface $stubTheliaAdapter */
        $stubTheliaAdapter = $this->getMock(
            'Thelia\Coupon\CouponBaseAdapter',
            array('getNbArticlesInCart'),
            array()
        );
        $stubTheliaAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue($nbArticlesInCart));

        return $stubTheliaAdapter;
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Coupon\Rule\AvailableForXArticles::checkBackOfficeInput
     *
     */
    public function testValidBackOfficeInput()
    {
        $adapter = $this->stubTheliaAdapter;

        $validators = array(
            AvailableForXArticles::PARAM1_QUANTITY => new RuleValidator(
                Operators::SUPERIOR,
                new QuantityParam(
                    $adapter,
                    4
                )
            )
        );
        $rule = new AvailableForXArticles($adapter, $validators);

        $expected = true;
        $actual = $rule->checkBackOfficeInput();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Coupon\Rule\AvailableForXArticles::checkBackOfficeInput
     * @expectedException \Thelia\Exception\InvalidRuleValueException
     */
    public function testInValidBackOfficeInputFloat()
    {
        $adapter = $this->stubTheliaAdapter;

        $validators = array(
            AvailableForXArticles::PARAM1_QUANTITY => new RuleValidator(
                Operators::SUPERIOR,
                new QuantityParam(
                    $adapter,
                    4.5
                )
            )
        );
        $rule = new AvailableForXArticles($adapter, $validators);

        $expected = false;
        $actual = $rule->checkBackOfficeInput();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Coupon\Rule\AvailableForXArticles::checkBackOfficeInput
     * @expectedException \Thelia\Exception\InvalidRuleValueException
     */
    public function testInValidBackOfficeInputNegative()
    {
        $adapter = $this->stubTheliaAdapter;

        $validators = array(
            AvailableForXArticles::PARAM1_QUANTITY => new RuleValidator(
                Operators::SUPERIOR,
                new QuantityParam(
                    $adapter,
                    -1
                )
            )
        );
        $rule = new AvailableForXArticles($adapter, $validators);

        $expected = false;
        $actual = $rule->checkBackOfficeInput();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Coupon\Rule\AvailableForXArticles::checkBackOfficeInput
     * @expectedException \Thelia\Exception\InvalidRuleValueException
     */
    public function testInValidBackOfficeInputString()
    {
        $adapter = $this->stubTheliaAdapter;

        $validators = array(
            AvailableForXArticles::PARAM1_QUANTITY => new RuleValidator(
                Operators::SUPERIOR,
                new QuantityParam(
                    $adapter,
                    'bad'
                )
            )
        );
        $rule = new AvailableForXArticles($adapter, $validators);

        $expected = false;
        $actual = $rule->checkBackOfficeInput();
        $this->assertEquals($expected, $actual);
    }





    /**
     * Check if validity test on FrontOffice inputs are working
     *
     * @covers Thelia\Coupon\Rule\AvailableForXArticles::checkCheckoutInput
     */
    public function testValidCheckoutInput()
    {
        $adapter = $this->stubTheliaAdapter;
        $validators = array(
            AvailableForXArticles::PARAM1_QUANTITY => new RuleValidator(
                Operators::SUPERIOR,
                new QuantityParam(
                    $adapter,
                    4
                )
            )
        );
        $rule = new AvailableForXArticles($adapter, $validators);

        $expected = true;
        $actual = $rule->checkCheckoutInput();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on FrontOffice inputs are working
     *
     * @covers Thelia\Coupon\Rule\AvailableForXArticles::checkCheckoutInput
     * @expectedException \Thelia\Exception\InvalidRuleValueException
     */
    public function testInValidCheckoutInputFloat()
    {
        $adapter = $this->generateValidCouponBaseAdapterMock(4.5);
        $validators = array(
            AvailableForXArticles::PARAM1_QUANTITY => new RuleValidator(
                Operators::SUPERIOR,
                new QuantityParam(
                    $adapter,
                    4
                )
            )
        );
        $rule = new AvailableForXArticles($adapter, $validators);

        $expected = false;
        $actual = $rule->checkCheckoutInput();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on FrontOffice inputs are working
     *
     * @covers Thelia\Coupon\Rule\AvailableForXArticles::checkCheckoutInput
     * @expectedException \Thelia\Exception\InvalidRuleValueException
     */
    public function testInValidCheckoutInputNegative()
    {
        $adapter = $this->generateValidCouponBaseAdapterMock(-1);

        $validators = array(
            AvailableForXArticles::PARAM1_QUANTITY => new RuleValidator(
                Operators::SUPERIOR,
                new QuantityParam(
                    $adapter,
                    4
                )
            )
        );
        $rule = new AvailableForXArticles($adapter, $validators);

        $expected = false;
        $actual = $rule->checkCheckoutInput();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on FrontOffice inputs are working
     *
     * @covers Thelia\Coupon\Rule\AvailableForXArticles::checkCheckoutInput
     * @expectedException \Thelia\Exception\InvalidRuleValueException
     */
    public function testInValidCheckoutInputString()
    {
        $adapter = $this->generateValidCouponBaseAdapterMock('bad');

        $validators = array(
            AvailableForXArticles::PARAM1_QUANTITY => new RuleValidator(
                Operators::SUPERIOR,
                new QuantityParam(
                    $adapter,
                    4
                )
            )
        );
        $rule = new AvailableForXArticles($adapter, $validators);

        $expected = false;
        $actual = $rule->checkCheckoutInput();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Coupon\Rule\AvailableForXArticles::isMatching
     *
     */
    public function testMatchingRuleInferior()
    {
        $adapter = $this->stubTheliaAdapter;
        $validators = array(
            AvailableForXArticles::PARAM1_QUANTITY => new RuleValidator(
                Operators::INFERIOR,
                new QuantityParam(
                    $adapter,
                    5
                )
            )
        );
        $rule = new AvailableForXArticles($adapter, $validators);

        $expected = true;
        $actual = $rule->isMatching();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test equals operator is working
     *
     * @covers Thelia\Coupon\Rule\AvailableForXArticles::isMatching
     *
     */
    public function testMatchingRuleEqual()
    {
        $adapter = $this->stubTheliaAdapter;
        $validators = array(
            AvailableForXArticles::PARAM1_QUANTITY => new RuleValidator(
                Operators::EQUAL,
                new QuantityParam(
                    $adapter,
                    4
                )
            )
        );
        $rule = new AvailableForXArticles($adapter, $validators);

        $expected = true;
        $actual = $rule->isMatching();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Coupon\Rule\AvailableForXArticles::isMatching
     *
     */
    public function testMatchingRuleSuperior()
    {
        $adapter = $this->stubTheliaAdapter;
        $validators = array(
            AvailableForXArticles::PARAM1_QUANTITY => new RuleValidator(
                Operators::SUPERIOR,
                new QuantityParam(
                    $adapter,
                    3
                )
            )
        );
        $rule = new AvailableForXArticles($adapter, $validators);

        $expected = true;
        $actual = $rule->isMatching();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test unavailable operator is working
     *
     * @covers Thelia\Coupon\Rule\AvailableForXArticles::isMatching
     * @expectedException \Thelia\Exception\InvalidRuleOperatorException
     *
     */
    public function testNotMatchingRule()
    {
        $adapter = $this->stubTheliaAdapter;
        $validators = array(
            AvailableForXArticles::PARAM1_QUANTITY => new RuleValidator(
                Operators::DIFFERENT,
                new QuantityParam(
                    $adapter,
                    3
                )
            )
        );
        $rule = new AvailableForXArticles($adapter, $validators);

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
