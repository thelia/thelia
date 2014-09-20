<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

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
            MatchForXArticles::CART_QUANTITY => Operators::IN
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 5
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
            MatchForXArticles::CART_QUANTITY => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 'X'
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
            MatchForXArticles::CART_QUANTITY => Operators::INFERIOR
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 5
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
            MatchForXArticles::CART_QUANTITY => Operators::INFERIOR
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 4,
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
            MatchForXArticles::CART_QUANTITY => Operators::INFERIOR_OR_EQUAL,
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 5,
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
            MatchForXArticles::CART_QUANTITY => Operators::INFERIOR_OR_EQUAL
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 4
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
            MatchForXArticles::CART_QUANTITY => Operators::INFERIOR_OR_EQUAL
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 3
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
            MatchForXArticles::CART_QUANTITY => Operators::EQUAL
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 4
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
            MatchForXArticles::CART_QUANTITY => Operators::EQUAL
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 5
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
            MatchForXArticles::CART_QUANTITY => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 4
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
            MatchForXArticles::CART_QUANTITY => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 3
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
            MatchForXArticles::CART_QUANTITY => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 5
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
            MatchForXArticles::CART_QUANTITY => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 3
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
            MatchForXArticles::CART_QUANTITY => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 4
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
            MatchForXArticles::CART_QUANTITY => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 4
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
            MatchForXArticles::CART_QUANTITY => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 4
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
            MatchForXArticles::CART_QUANTITY => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticles::CART_QUANTITY => 4
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $actual = $condition1->getValidators();

        $validators = array(
            'inputs' => array(
                MatchForXArticles::CART_QUANTITY => array(
                    'availableOperators' => array(
                        '<' => 'Price',
                        '<=' => 'Price',
                        '==' => 'Price',
                        '>=' => 'Price',
                        '>' => 'Price'
                    ),
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
