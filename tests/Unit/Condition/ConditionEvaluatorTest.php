<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Unit\Condition;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Condition\Operators;

final class ConditionEvaluatorTest extends TestCase
{
    #[DataProvider('comparisons')]
    public function testVariableOpComparison(mixed $a, string $op, mixed $b, bool $expected): void
    {
        self::assertSame($expected, (new ConditionEvaluator())->variableOpComparison($a, $op, $b));
    }

    /**
     * @return iterable<string, array{mixed, string, mixed, bool}>
     */
    public static function comparisons(): iterable
    {
        yield 'equal true' => [10, Operators::EQUAL, 10, true];
        yield 'equal different types' => [10, Operators::EQUAL, '10', false];
        yield 'different true' => [10, Operators::DIFFERENT, 11, true];
        yield 'different same value type' => [10, Operators::DIFFERENT, 10, false];
        yield 'superior strict true' => [11, Operators::SUPERIOR, 10, true];
        yield 'superior strict false' => [10, Operators::SUPERIOR, 10, false];
        yield 'superior or equal true' => [10, Operators::SUPERIOR_OR_EQUAL, 10, true];
        yield 'inferior strict true' => [9, Operators::INFERIOR, 10, true];
        yield 'inferior strict false' => [10, Operators::INFERIOR, 10, false];
        yield 'inferior or equal true' => [10, Operators::INFERIOR_OR_EQUAL, 10, true];
        yield 'in true' => ['b', Operators::IN, ['a', 'b', 'c'], true];
        yield 'in false' => ['z', Operators::IN, ['a', 'b', 'c'], false];
        yield 'out true' => ['z', Operators::OUT, ['a', 'b'], true];
        yield 'out false' => ['a', Operators::OUT, ['a', 'b'], false];
    }

    public function testVariableOpComparisonThrowsOnUnknownOperator(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unrecognized operator');

        (new ConditionEvaluator())->variableOpComparison(1, 'invalid', 2);
    }

    public function testIsMatchingReturnsTrueForEmptyCollection(): void
    {
        self::assertTrue((new ConditionEvaluator())->isMatching(new ConditionCollection()));
    }

    public function testIsMatchingReturnsTrueWhenEveryConditionIsTrue(): void
    {
        $collection = new ConditionCollection();
        $collection[] = $this->makeCondition(true);
        $collection[] = $this->makeCondition(true);

        self::assertTrue((new ConditionEvaluator())->isMatching($collection));
    }

    public function testIsMatchingReturnsFalseAsSoonAsOneConditionFails(): void
    {
        $collection = new ConditionCollection();
        $collection[] = $this->makeCondition(true);
        $collection[] = $this->makeCondition(false);
        $collection[] = $this->makeCondition(true);

        self::assertFalse((new ConditionEvaluator())->isMatching($collection));
    }

    private function makeCondition(bool $matches): ConditionInterface
    {
        $condition = $this->createMock(ConditionInterface::class);
        $condition->method('isMatching')->willReturn($matches);

        return $condition;
    }
}
