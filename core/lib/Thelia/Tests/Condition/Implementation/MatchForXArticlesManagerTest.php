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
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test MatchForXArticlesManager Class
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class MatchForXArticlesManagerTest extends \PHPUnit_Framework_TestCase
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
     * @covers Thelia\Condition\Implementation\MatchForXArticlesManager::setValidators
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

        $condition1 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => Operators::IN
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 5
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
     * @covers Thelia\Condition\Implementation\MatchForXArticlesManager::setValidators
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

        $condition1 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 'X'
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
     * @covers Thelia\Condition\Implementation\MatchForXArticlesManager::isMatching
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

        $condition1 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => Operators::INFERIOR
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 5
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
     * @covers Thelia\Condition\Implementation\MatchForXArticlesManager::isMatching
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

        $condition1 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => Operators::INFERIOR
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 4,
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
     * @covers Thelia\Condition\Implementation\MatchForXArticlesManager::isMatching
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

        $condition1 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => Operators::INFERIOR_OR_EQUAL,
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 5,
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
     * @covers Thelia\Condition\Implementation\MatchForXArticlesManager::isMatching
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

        $condition1 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => Operators::INFERIOR_OR_EQUAL
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 4
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
     * @covers Thelia\Condition\Implementation\MatchForXArticlesManager::isMatching
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

        $condition1 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => Operators::INFERIOR_OR_EQUAL
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 3
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
     * @covers Thelia\Condition\Implementation\MatchForXArticlesManager::isMatching
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

        $condition1 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => Operators::EQUAL
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 4
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
     * @covers Thelia\Condition\Implementation\MatchForXArticlesManager::isMatching
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

        $condition1 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => Operators::EQUAL
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 5
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
     * @covers Thelia\Condition\Implementation\MatchForXArticlesManager::isMatching
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

        $condition1 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 4
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
     * @covers Thelia\Condition\Implementation\MatchForXArticlesManager::isMatching
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

        $condition1 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 3
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
     * @covers Thelia\Condition\Implementation\MatchForXArticlesManager::isMatching
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

        $condition1 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => Operators::SUPERIOR_OR_EQUAL
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 5
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
     * @covers Thelia\Condition\Implementation\MatchForXArticlesManager::isMatching
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

        $condition1 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 3
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
     * @covers Thelia\Condition\Implementation\MatchForXArticlesManager::isMatching
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

        $condition1 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 4
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

        $condition1 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 4
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

        $condition1 = new MatchForXArticlesManager($stubFacade);
        $operators = array(
            MatchForXArticlesManager::INPUT1 => Operators::SUPERIOR
        );
        $values = array(
            MatchForXArticlesManager::INPUT1 => 4
        );
        $condition1->setValidatorsFromForm($operators, $values);

        $expected = array(
            MatchForXArticlesManager::INPUT1 => array(
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
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

}
