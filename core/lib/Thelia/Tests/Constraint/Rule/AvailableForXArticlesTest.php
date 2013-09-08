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

use Thelia\Constraint\Rule\AvailableForXArticlesManager;
use Thelia\Constraint\Rule\Operators;

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

//    /** @var CouponAdapterInterface $stubTheliaAdapter */
//    protected $stubTheliaAdapter = null;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
//        /** @var CouponAdapterInterface $stubTheliaAdapter */
//        $this->stubTheliaAdapter = $this->generateValidCouponBaseAdapterMock();
    }

//    /**
//     * Generate valid CouponBaseAdapter
//     *
//     * @param int $nbArticlesInCart Total articles in the current Cart
//     *
//     * @return CouponAdapterInterface
//     */
//    protected function generateValidCouponBaseAdapterMock($nbArticlesInCart = 4)
//    {
//        /** @var CouponAdapterInterface $stubTheliaAdapter */
//        $stubTheliaAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
//            ->disableOriginalConstructor()
//            ->setMethods(array('getNbArticlesInCart'))
//            ->getMock();
//        $stubTheliaAdapter->expects($this->any())
//            ->method('getNbArticlesInCart')
//            ->will($this->returnValue($nbArticlesInCart));
//
//        return $stubTheliaAdapter;
//    }

//    /**
//     * Check if validity test on BackOffice inputs are working
//     *
//     * @covers Thelia\Coupon\Rule\AvailableForXArticles::checkBackOfficeInput
//     *
//     */
//    public function testValidBackOfficeInput()
//    {
//        $translator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        $rule = new AvailableForXArticles($translator);
//        $operators = array(AvailableForXArticles::PARAM1_QUANTITY => Operators::SUPERIOR);
//        $values = array(
//            AvailableForXArticles::PARAM1_QUANTITY => 4
//        );
//        $rule->populateFromForm($operators, $values);
//
//        $expected = true;
//        $actual = $rule->checkBackOfficeInput();
//        $this->assertEquals($expected, $actual);
//    }

//    /**
//     * Check if validity test on BackOffice inputs are working
//     *
//     * @covers Thelia\Coupon\Rule\AvailableForXArticles::checkBackOfficeInput
//     * @expectedException \Thelia\Exception\InvalidRuleValueException
//     */
//    public function testInValidBackOfficeInputFloat()
//    {
//        $adapter = $this->stubTheliaAdapter;
//
//        $validators = array(
//            AvailableForXArticles::PARAM1_QUANTITY => new RuleValidator(
//                Operators::SUPERIOR,
//                new QuantityParam(
//                    $adapter,
//                    4.5
//                )
//            )
//        );
//        $rule = new AvailableForXArticles($adapter, $validators);
//
//        $expected = false;
//        $actual = $rule->checkBackOfficeInput();
//        $this->assertEquals($expected, $actual);
//    }

//    /**
//     * Check if validity test on BackOffice inputs are working
//     *
//     * @covers Thelia\Coupon\Rule\AvailableForXArticles::checkBackOfficeInput
//     * @expectedException \Thelia\Exception\InvalidRuleValueException
//     */
//    public function testInValidBackOfficeInputNegative()
//    {
//        $adapter = $this->stubTheliaAdapter;
//
//        $validators = array(
//            AvailableForXArticles::PARAM1_QUANTITY => new RuleValidator(
//                Operators::SUPERIOR,
//                new QuantityParam(
//                    $adapter,
//                    -1
//                )
//            )
//        );
//        $rule = new AvailableForXArticles($adapter, $validators);
//
//        $expected = false;
//        $actual = $rule->checkBackOfficeInput();
//        $this->assertEquals($expected, $actual);
//    }

//    /**
//     * Check if validity test on BackOffice inputs are working
//     *
//     * @covers Thelia\Coupon\Rule\AvailableForXArticles::checkBackOfficeInput
//     * @expectedException \Thelia\Exception\InvalidRuleValueException
//     */
//    public function testInValidBackOfficeInputString()
//    {
//        $adapter = $this->stubTheliaAdapter;
//
//        $validators = array(
//            AvailableForXArticles::PARAM1_QUANTITY => new RuleValidator(
//                Operators::SUPERIOR,
//                new QuantityParam(
//                    $adapter,
//                    'bad'
//                )
//            )
//        );
//        $rule = new AvailableForXArticles($adapter, $validators);
//
//        $expected = false;
//        $actual = $rule->checkBackOfficeInput();
//        $this->assertEquals($expected, $actual);
//    }





    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testMatchingRuleInferior()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::INFERIOR
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 5
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testNotMatchingRuleInferior()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::INFERIOR
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 4,
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testMatchingRuleInferiorEquals()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::INFERIOR_OR_EQUAL,
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 5,
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testMatchingRuleInferiorEquals2()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::INFERIOR_OR_EQUAL
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 4
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testNotMatchingRuleInferiorEquals()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::INFERIOR_OR_EQUAL
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 3
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test equals operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testMatchingRuleEqual()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::EQUAL
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 4
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test equals operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testNotMatchingRuleEqual()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::EQUAL
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 5
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testMatchingRuleSuperiorEquals()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 4
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testMatchingRuleSuperiorEquals2()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 3
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testNotMatchingRuleSuperiorEquals()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 5
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }


    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testMatchingRuleSuperior()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 3
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Constraint\Rule\AvailableForXArticlesManager::isMatching
     *
     */
    public function testNotMatchingRuleSuperior()
    {
        $stubAdapter = $this->getMockBuilder('\Thelia\Coupon\CouponBaseAdapter')
            ->disableOriginalConstructor()
            ->getMock();

        $stubAdapter->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));

        $rule1 = new AvailableForXArticlesManager($stubAdapter);
        $operators = array(
            AvailableForXArticlesManager::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            AvailableForXArticlesManager::INPUT1 => 4
        );
        $rule1->setValidatorsFromForm($operators, $values);

        $isValid = $rule1->isMatching();

        $expected = false;
        $actual =$isValid;
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
