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

namespace Thelia\Condition\Implementation;

use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\Operators;
use Thelia\Condition\SerializableCondition;
use Thelia\Coupon\FacadeInterface;

/**
 * Unit Test MatchForXArticles Class
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class MatchForXArticlesTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {

    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::setValidators
     * @expectedException \Thelia\Exception\InvalidConditionOperatorException
     */
    public function testInValidBackOfficeInputOperator()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        /** @var FacadeInterface $stubFacade */
        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::IN
        );
        $values = array(
            MatchForXArticles::INPUT1 => 5
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if validity test on BackOffice inputs are working
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::setValidators
     * @expectedException \Thelia\Exception\InvalidConditionValueException
     */
    public function testInValidBackOfficeInputValue()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticles::INPUT1 => 'X'
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::isMatching
     *
     */
    public function testMatchingRuleInferior()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::INFERIOR
        );
        $values = array(
            MatchForXArticles::INPUT1 => 5
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::isMatching
     *
     */
    public function testNotMatchingRuleInferior()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::INFERIOR
        );
        $values = array(
            MatchForXArticles::INPUT1 => 4,
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::isMatching
     *
     */
    public function testMatchingRuleInferiorEquals()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::INFERIOR_OR_EQUAL,
        );
        $values = array(
            MatchForXArticles::INPUT1 => 5,
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::isMatching
     *
     */
    public function testMatchingRuleInferiorEquals2()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::INFERIOR_OR_EQUAL
        );
        $values = array(
            MatchForXArticles::INPUT1 => 4
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test inferior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::isMatching
     *
     */
    public function testNotMatchingRuleInferiorEquals()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::INFERIOR_OR_EQUAL
        );
        $values = array(
            MatchForXArticles::INPUT1 => 3
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test equals operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::isMatching
     *
     */
    public function testMatchingRuleEqual()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::EQUAL
        );
        $values = array(
            MatchForXArticles::INPUT1 => 4
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test equals operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::isMatching
     *
     */
    public function testNotMatchingRuleEqual()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::EQUAL
        );
        $values = array(
            MatchForXArticles::INPUT1 => 5
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::isMatching
     *
     */
    public function testMatchingRuleSuperiorEquals()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            MatchForXArticles::INPUT1 => 4
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::isMatching
     *
     */
    public function testMatchingRuleSuperiorEquals2()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            MatchForXArticles::INPUT1 => 3
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::isMatching
     *
     */
    public function testNotMatchingRuleSuperiorEquals()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            MatchForXArticles::INPUT1 => 5
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::isMatching
     *
     */
    public function testMatchingRuleSuperior()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticles::INPUT1 => 3
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = true;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check if test superior operator is working
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::isMatching
     *
     */
    public function testNotMatchingRuleSuperior()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticles::INPUT1 => 4
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $isValid = $condition1->isMatching();

        $expected = false;
        $actual =$isValid;
        $this->assertEquals($expected, $actual);
    }

    public function testGetSerializableRule()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticles::INPUT1 => 4
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $serializableRule = $condition1->getSerializableCondition();

        $expected = new SerializableCondition();
        $expected->conditionServiceId = $condition1->getServiceId();
        $expected->operators = $operators;
        $expected->values = $values;

        $actual = $serializableRule;

        $this->assertEquals($expected, $actual);

    }

    public function testGetAvailableOperators()
    {
        /** @var FacadeInterface $stubFacade */
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getNbArticlesInCart')
            ->will($this->returnValue(4));
        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticles::INPUT1 => 4
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $expected = array(
            MatchForXArticles::INPUT1 => array(
                Operators::INFERIOR,
                Operators::INFERIOR_OR_EQUAL,
                Operators::EQUAL,
                Operators::SUPERIOR_OR_EQUAL,
                Operators::SUPERIOR
            )
        );
        $actual = $condition1->getAvailableOperators();

        $this->assertEquals($expected, $actual);

    }

    /**
     * Generate adapter stub
     *
     * @param int    $cartTotalPrice   Cart total price
     * @param string $checkoutCurrency Checkout currency
     * @param string $i18nOutput       Output from each translation
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function generateFacadeStub($cartTotalPrice = 400, $checkoutCurrency = 'EUR', $i18nOutput = '')
    {
        $stubFacade = $this->getMockBuilder('\Thelia\Coupon\BaseFacade')
            ->disableOriginalConstructor()
            ->getMock();

        $stubFacade->expects($this->any())
            ->method('getCartTotalPrice')
            ->will($this->returnValue($cartTotalPrice));

        $stubFacade->expects($this->any())
            ->method('getCheckoutCurrency')
            ->will($this->returnValue($checkoutCurrency));

        $stubFacade->expects($this->any())
            ->method('getConditionEvaluator')
            ->will($this->returnValue(new ConditionEvaluator()));

        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();
        $stubTranslator->expects($this->any())
            ->method('trans')
            ->will($this->returnValue($i18nOutput));

        $stubFacade->expects($this->any())
            ->method('getTranslator')
            ->will($this->returnValue($stubTranslator));

        return $stubFacade;
    }

    /**
     * Check getName i18n
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::getName
     *
     */
    public function testGetName()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'Number of articles in cart');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchForXArticles($stubFacade);

        $actual = $condition1->getName();
        $expected = 'Number of articles in cart';
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check tooltip i18n
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::getToolTip
     *
     */
    public function testGetToolTip()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'If cart products quantity is <strong>superior to</strong> 4');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticles::INPUT1 => 4
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $actual = $condition1->getToolTip();
        $expected = 'If cart products quantity is <strong>superior to</strong> 4';
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check validator
     *
     * @covers Thelia\Condition\Implementation\MatchForXArticles::generateInputs
     *
     */
    public function testGetValidator()
    {
        $stubFacade = $this->generateFacadeStub(399, 'EUR', 'Price');

        /** @var FacadeInterface $stubFacade */
        $condition1 = new MatchForXArticles($stubFacade);
        $operators = array(
            MatchForXArticles::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticles::INPUT1 => 4
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $actual = $condition1->getValidators();

        $validators = array(
            'inputs' => array(
                MatchForXArticles::INPUT1 => array(
                    'title' => 'Price',
                    'availableOperators' => array(
                        '<' => 'Price',
                        '<=' => 'Price',
                        '==' => 'Price',
                        '>=' => 'Price',
                        '>' => 'Price'
                    ),
                    'type' => 'text',
                    'class' => 'form-control',
                    'value' => '',
                    'selectedOperator' => ''
                )
            ),
            'setOperators' => array(
                'quantity' => '>'
            ),
            'setValues' => array(
                'quantity' => 4
            )
        );
        $expected = $validators;

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
