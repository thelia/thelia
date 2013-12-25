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
use Thelia\Condition\ConditionCollection;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Unit Test ConditionEvaluator Class
 *
 * @package Constraint
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class ConditionEvaluatorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    public function setUp()
    {
    }

    /**
     * Test vatiable comparison
     *
     * @covers Thelia\Condition\ConditionEvaluator::variableOpComparison
     */
    public function testVariableOpComparisonSuccess()
    {
        $conditionEvaluator = new ConditionEvaluator();
        $expected = true;
        $actual = $conditionEvaluator->variableOpComparison(1, Operators::EQUAL, 1);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::DIFFERENT, 2);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::SUPERIOR, 0);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::INFERIOR, 2);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::INFERIOR_OR_EQUAL, 1);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::INFERIOR_OR_EQUAL, 2);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::SUPERIOR_OR_EQUAL, 1);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::SUPERIOR_OR_EQUAL, 0);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::IN, array(1, 2, 3));
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::OUT, array(0, 2, 3));
        $this->assertEquals($expected, $actual);

    }

    /**
     * Test vatiable comparison
     *
     * @covers Thelia\Condition\ConditionEvaluator::variableOpComparison
     */
    public function testVariableOpComparisonFail()
    {
        $conditionEvaluator = new ConditionEvaluator();
        $expected = false;
        $actual = $conditionEvaluator->variableOpComparison(2, Operators::EQUAL, 1);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(2, Operators::DIFFERENT, 2);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(0, Operators::SUPERIOR, 0);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(3, Operators::INFERIOR, 2);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(2, Operators::INFERIOR_OR_EQUAL, 1);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(3, Operators::SUPERIOR_OR_EQUAL, 4);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(0, Operators::IN, array(1, 2, 3));
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(2, Operators::OUT, array(0, 2, 3));
        $this->assertEquals($expected, $actual);

    }

    /**
     * Test vatiable comparison
     *
     * @expectedException \Exception
     * @covers Thelia\Condition\ConditionEvaluator::variableOpComparison
     */
    public function testVariableOpComparisonException()
    {
        $conditionEvaluator = new ConditionEvaluator();
        $expected = true;
        $actual = $conditionEvaluator->variableOpComparison(1, 'bad', 1);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test condition collection matching
     *
     * @covers Thelia\Condition\ConditionEvaluator::isMatching
     */
    public function testIsMatchingTrue()
    {
        $stubConditionTrue1 = $this->getMockBuilder('\Thelia\Condition\Implementation\MatchForXArticles')
            ->disableOriginalConstructor()
            ->getMock();
        $stubConditionTrue1->expects($this->any())
            ->method('isMatching')
            ->will($this->returnValue(true));

        $stubConditionTrue2 = $this->getMockBuilder('\Thelia\Condition\Implementation\MatchForXArticles')
            ->disableOriginalConstructor()
            ->getMock();
        $stubConditionTrue2->expects($this->any())
            ->method('isMatching')
            ->will($this->returnValue(true));

        $collection = new ConditionCollection();
        $collection->add($stubConditionTrue1);
        $collection->add($stubConditionTrue2);

        $conitionEvaluator = new ConditionEvaluator();
        $actual = $conitionEvaluator->isMatching($collection);

        $this->assertTrue($actual);
    }

    /**
     * Test condition collection matching
     *
     * @covers Thelia\Condition\ConditionEvaluator::isMatching
     */
    public function testIsMatchingFalse()
    {
        $stubConditionTrue = $this->getMockBuilder('\Thelia\Condition\Implementation\MatchForXArticles')
            ->disableOriginalConstructor()
            ->getMock();
        $stubConditionTrue->expects($this->any())
            ->method('isMatching')
            ->will($this->returnValue(true));

        $stubConditionFalse = $this->getMockBuilder('\Thelia\Condition\Implementation\MatchForXArticles')
            ->disableOriginalConstructor()
            ->getMock();
        $stubConditionFalse->expects($this->any())
            ->method('isMatching')
            ->will($this->returnValue(false));

        $collection = new ConditionCollection();
        $collection->add($stubConditionTrue);
        $collection->add($stubConditionFalse);

        $conitionEvaluator = new ConditionEvaluator();
        $actual = $conitionEvaluator->isMatching($collection);

        $this->assertFalse($actual);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }
}
