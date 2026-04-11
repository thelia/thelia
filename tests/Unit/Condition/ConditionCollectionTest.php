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

use PHPUnit\Framework\TestCase;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Condition\SerializableCondition;

final class ConditionCollectionTest extends TestCase
{
    public function testAnEmptyCollectionHasCountZero(): void
    {
        self::assertCount(0, new ConditionCollection());
    }

    public function testOffsetSetAppendsWhenNoOffsetIsGiven(): void
    {
        $collection = new ConditionCollection();
        $first = $this->makeCondition();

        $collection[] = $first;

        self::assertCount(1, $collection);
        self::assertSame($first, $collection[0]);
    }

    public function testOffsetSetReplacesAtGivenOffset(): void
    {
        $collection = new ConditionCollection();
        $collection[0] = $this->makeCondition();

        $replacement = $this->makeCondition();
        $collection[0] = $replacement;

        self::assertCount(1, $collection);
        self::assertSame($replacement, $collection[0]);
    }

    public function testOffsetExistsAndUnset(): void
    {
        $collection = new ConditionCollection();
        $collection[] = $this->makeCondition();

        self::assertTrue(isset($collection[0]));
        unset($collection[0]);
        self::assertFalse(isset($collection[0]));
        self::assertNull($collection[0]);
    }

    public function testIterationYieldsAllConditionsInInsertionOrder(): void
    {
        $a = $this->makeCondition();
        $b = $this->makeCondition();
        $c = $this->makeCondition();

        $collection = new ConditionCollection();
        $collection[] = $a;
        $collection[] = $b;
        $collection[] = $c;

        self::assertSame([$a, $b, $c], iterator_to_array($collection, false));
    }

    public function testToStringReturnsJsonOfSerializableConditions(): void
    {
        $condition = $this->makeCondition();
        $serialized = new SerializableCondition();
        $serialized->conditionServiceId = 'thelia.condition.example';
        $serialized->operators = [];
        $serialized->values = [];

        $condition->method('getSerializableCondition')->willReturn($serialized);

        $collection = new ConditionCollection();
        $collection[] = $condition;

        $json = (string) $collection;
        $decoded = json_decode($json, true);

        self::assertIsArray($decoded);
        self::assertCount(1, $decoded);
        self::assertSame('thelia.condition.example', $decoded[0]['conditionServiceId']);
    }

    private function makeCondition(): ConditionInterface
    {
        return $this->createMock(ConditionInterface::class);
    }
}
