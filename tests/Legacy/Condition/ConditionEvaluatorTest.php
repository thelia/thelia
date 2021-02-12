<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Condition;

use PHPUnit\Framework\TestCase;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\Operators;

/**
 * Unit Test ConditionEvaluator Class.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class ConditionEvaluatorTest extends TestCase
{
    /**
     * Test variable comparison.
     *
     * @covers \Thelia\Condition\ConditionEvaluator::variableOpComparison
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

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::IN, [1, 2, 3]);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(1, Operators::OUT, [0, 2, 3]);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test variable comparison.
     *
     * @covers \Thelia\Condition\ConditionEvaluator::variableOpComparison
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

        $actual = $conditionEvaluator->variableOpComparison(0, Operators::IN, [1, 2, 3]);
        $this->assertEquals($expected, $actual);

        $actual = $conditionEvaluator->variableOpComparison(2, Operators::OUT, [0, 2, 3]);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test variable comparison.
     *
     * @covers \Thelia\Condition\ConditionEvaluator::variableOpComparison
     */
    public function testVariableOpComparisonException()
    {
        $conditionEvaluator = new ConditionEvaluator();
        $this->expectException(\Exception::class);
        $actual = $conditionEvaluator->variableOpComparison(1, 'bad', 1);
        $this->assertEquals(true, $actual);
    }

    /**
     * Test condition collection matching.
     *
     * @covers \Thelia\Condition\ConditionEvaluator::isMatching
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
        $collection[] = $stubConditionTrue1;
        $collection[] = $stubConditionTrue2;

        $conditionEvaluator = new ConditionEvaluator();
        $actual = $conditionEvaluator->isMatching($collection);

        $this->assertTrue($actual);
    }

    /**
     * Test condition collection matching.
     *
     * @covers \Thelia\Condition\ConditionEvaluator::isMatching
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
        $collection[] = $stubConditionTrue;
        $collection[] = $stubConditionFalse;

        $conditionEvaluator = new ConditionEvaluator();
        $actual = $conditionEvaluator->isMatching($collection);

        $this->assertFalse($actual);
    }
}
