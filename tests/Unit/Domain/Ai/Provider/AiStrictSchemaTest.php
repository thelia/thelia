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

namespace Thelia\Tests\Unit\Domain\Ai\Provider;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\Provider\AiStrictSchema;

final class AiStrictSchemaTest extends TestCase
{
    /** @return iterable<string, array{array<string, mixed>, bool}> */
    public static function schemas(): iterable
    {
        yield 'closed, every property required' => [['type' => 'object', 'properties' => ['a' => ['type' => 'string']], 'required' => ['a'], 'additionalProperties' => false], true];
        yield 'open root' => [['type' => 'object', 'properties' => ['a' => ['type' => 'string']], 'required' => ['a']], false];
        yield 'closed root with an optional property' => [['type' => 'object', 'properties' => ['a' => ['type' => 'string'], 'b' => ['type' => 'string']], 'required' => ['a'], 'additionalProperties' => false], false];
        yield 'closed root without a required list' => [['type' => 'object', 'properties' => ['a' => ['type' => 'string']], 'additionalProperties' => false], false];
        yield 'closed root without property' => [['type' => 'object', 'additionalProperties' => false], true];
        yield 'nested object left open' => [['type' => 'object', 'properties' => ['child' => ['type' => 'object', 'properties' => ['x' => ['type' => 'string']], 'required' => ['x']]], 'required' => ['child'], 'additionalProperties' => false], false];
        yield 'nested object with an optional property' => [['type' => 'object', 'properties' => ['child' => ['type' => 'object', 'properties' => ['x' => ['type' => 'string'], 'y' => ['type' => 'string']], 'required' => ['x'], 'additionalProperties' => false]], 'required' => ['child'], 'additionalProperties' => false], false];
        yield 'nested objects all closed' => [['type' => 'object', 'properties' => ['child' => ['type' => 'object', 'properties' => ['x' => ['type' => 'string']], 'required' => ['x'], 'additionalProperties' => false]], 'required' => ['child'], 'additionalProperties' => false], true];
        yield 'open object in an array' => [['type' => 'object', 'properties' => ['rows' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['x' => ['type' => 'string']], 'required' => ['x']]]], 'required' => ['rows'], 'additionalProperties' => false], false];
        yield 'closed object in an array' => [['type' => 'object', 'properties' => ['rows' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['x' => ['type' => 'string']], 'required' => ['x'], 'additionalProperties' => false]]], 'required' => ['rows'], 'additionalProperties' => false], true];
        yield 'open object in an anyOf' => [['type' => 'object', 'properties' => ['v' => ['anyOf' => [['type' => 'null'], ['type' => 'object', 'properties' => ['x' => ['type' => 'string']], 'required' => ['x']]]]], 'required' => ['v'], 'additionalProperties' => false], false];
        yield 'open object in a definition' => [['type' => 'object', 'properties' => ['v' => ['$ref' => '#/$defs/item']], 'required' => ['v'], 'additionalProperties' => false, '$defs' => ['item' => ['type' => 'object', 'properties' => ['x' => ['type' => 'string']], 'required' => ['x']]]], false];
        yield 'closed object in a definition' => [['type' => 'object', 'properties' => ['v' => ['$ref' => '#/$defs/item']], 'required' => ['v'], 'additionalProperties' => false, '$defs' => ['item' => ['type' => 'object', 'properties' => ['x' => ['type' => 'string']], 'required' => ['x'], 'additionalProperties' => false]]], true];
        yield 'a property named like a keyword is not read as one' => [['type' => 'object', 'properties' => ['items' => ['type' => 'string']], 'required' => ['items'], 'additionalProperties' => false], true];
    }

    /**
     * @param array<string, mixed> $schema
     */
    #[DataProvider('schemas')]
    public function testStrictIsAllowedOnlyWhenEveryObjectIsClosedAndFullyRequired(array $schema, bool $expected): void
    {
        self::assertSame($expected, AiStrictSchema::accepts($schema));
    }
}
