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

namespace Thelia\Tests\Unit\Domain\Ai\Guardrail;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\Guardrail\AiSchemaValidator;

final class AiSchemaValidatorTest extends TestCase
{
    private const array PAGE = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['title', 'blocks'],
        'properties' => [
            'title' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 20],
            'status' => ['enum' => ['draft', 'published']],
            'rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
            'blocks' => ['type' => 'array', 'minItems' => 1, 'items' => ['$ref' => '#/$defs/block']],
        ],
        '$defs' => [
            'block' => ['type' => 'object', 'required' => ['kind'], 'properties' => ['kind' => ['const' => 'text'], 'body' => ['type' => ['string', 'null']]]],
        ],
    ];

    public function testASchemaDecodedToObjectsIsReadLikeOneDecodedToArrays(): void
    {
        $schema = self::PAGE;
        $schema['properties'] = (object) $schema['properties'];
        $schema['$defs'] = (object) ['block' => (object) ['type' => 'object', 'required' => ['kind'], 'properties' => (object) ['kind' => (object) ['const' => 'text']]]];
        $answer = ['title' => 'Our story', 'blocks' => [['kind' => 'text']]];

        self::assertSame([], (new AiSchemaValidator())->validate($schema, $answer));
        self::assertNotSame([], (new AiSchemaValidator())->validate($schema, $answer + ['extra' => 1]));
    }

    public function testAnAnswerThatMatchesTheSchemaHasNoError(): void
    {
        $data = ['title' => 'Our story', 'status' => 'draft', 'rating' => 5, 'blocks' => [['kind' => 'text', 'body' => null], ['kind' => 'text', 'body' => 'Hello']]];

        self::assertSame([], (new AiSchemaValidator())->validate(self::PAGE, $data));
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('mismatches')]
    public function testEachRuleOfTheSchemaRefusesWhatBreaksIt(array $data, string $expected): void
    {
        $errors = (new AiSchemaValidator())->validate(self::PAGE, $data);

        self::assertContains($expected, $errors);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function mismatches(): iterable
    {
        $valid = ['title' => 'Our story', 'blocks' => [['kind' => 'text']]];

        yield 'a required property is missing' => [['blocks' => [['kind' => 'text']]], '/title: required'];
        yield 'a wrong type' => [[...$valid, 'title' => 12], '/title: type'];
        yield 'too short' => [[...$valid, 'title' => 'ab'], '/title: minLength'];
        yield 'too long' => [[...$valid, 'title' => str_repeat('é', 21)], '/title: maxLength'];
        yield 'not in the enumeration' => [[...$valid, 'status' => 'archived'], '/status: enum'];
        yield 'below the minimum' => [[...$valid, 'rating' => 0], '/rating: minimum'];
        yield 'above the maximum' => [[...$valid, 'rating' => 6], '/rating: maximum'];
        yield 'a float is not an integer' => [[...$valid, 'rating' => 2.5], '/rating: type'];
        yield 'an unknown property when the schema closes the object' => [[...$valid, 'extra' => 1], '/extra: not allowed'];
        yield 'too few items' => [[...$valid, 'blocks' => []], '/blocks: minItems'];
        yield 'an item that breaks the referenced schema' => [[...$valid, 'blocks' => [['kind' => 'image']]], '/blocks/0/kind: const'];
        yield 'an item of the wrong type' => [[...$valid, 'blocks' => [['kind' => 'text', 'body' => 3]]], '/blocks/0/body: type'];
    }

    public function testAnIntegerWrittenWithADecimalPointIsStillAnInteger(): void
    {
        self::assertSame([], (new AiSchemaValidator())->validate(['type' => 'object', 'properties' => ['n' => ['type' => 'integer']]], ['n' => 3.0]));
    }

    public function testAnEmptyObjectAndAnEmptyListAreTheSameDecodedValue(): void
    {
        $validator = new AiSchemaValidator();

        self::assertSame([], $validator->validate(['type' => 'object', 'properties' => ['o' => ['type' => 'object']]], ['o' => []]));
        self::assertSame([], $validator->validate(['type' => 'object', 'properties' => ['l' => ['type' => 'array']]], ['l' => []]));
        self::assertSame(['/o: type'], $validator->validate(['type' => 'object', 'properties' => ['o' => ['type' => 'object']]], ['o' => [1, 2]]));
    }

    public function testCombinationsAndNegation(): void
    {
        $schema = ['type' => 'object', 'properties' => [
            'one' => ['oneOf' => [['type' => 'string'], ['type' => 'integer']]],
            'any' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
            'all' => ['allOf' => [['type' => 'string'], ['maxLength' => 2]]],
            'not' => ['not' => ['type' => 'null']],
        ]];
        $validator = new AiSchemaValidator();

        self::assertSame([], $validator->validate($schema, ['one' => 'a', 'any' => null, 'all' => 'ab', 'not' => 1]));
        self::assertSame(['/one: oneOf', '/any: anyOf', '/all: maxLength', '/not: not'], $validator->validate($schema, ['one' => true, 'any' => 1, 'all' => 'abc', 'not' => null]));
    }

    public function testTheItemsAreUniqueWhenTheSchemaSaysSo(): void
    {
        $schema = ['type' => 'object', 'properties' => ['tags' => ['type' => 'array', 'uniqueItems' => true]]];

        self::assertSame([], (new AiSchemaValidator())->validate($schema, ['tags' => ['a', 'b']]));
        self::assertSame(['/tags: uniqueItems'], (new AiSchemaValidator())->validate($schema, ['tags' => ['a', 'b', 'a']]));
    }

    public function testAReferenceOutsideTheSchemaFailsTheAnswerInsteadOfPassingIt(): void
    {
        $schema = ['type' => 'object', 'properties' => ['x' => ['$ref' => 'https://example.com/schema.json']]];

        self::assertSame(['/x: unresolved $ref'], (new AiSchemaValidator())->validate($schema, ['x' => 'anything']));
    }

    public function testAFormatAndAnUnknownKeywordAreAnnotationsNotRules(): void
    {
        $schema = ['type' => 'object', 'properties' => ['mail' => ['type' => 'string', 'format' => 'email', 'x-note' => 'free']]];

        self::assertSame([], (new AiSchemaValidator())->validate($schema, ['mail' => 'not an email']));
    }

    public function testThePatternIsAnchoredByTheSchemaAndEscapedForTheDelimiter(): void
    {
        $schema = ['type' => 'object', 'properties' => ['slug' => ['type' => 'string', 'pattern' => '^[a-z~-]+$']]];

        self::assertSame([], (new AiSchemaValidator())->validate($schema, ['slug' => 'a~b-c']));
        self::assertSame(['/slug: pattern'], (new AiSchemaValidator())->validate($schema, ['slug' => 'A']));
    }

    public function testASchemaThatRecursesStopsAtADepthInsteadOfLoopingForever(): void
    {
        $schema = ['type' => 'object', 'properties' => ['next' => ['$ref' => '#']]];
        $data = [];
        $node = &$data;

        for ($level = 0; $level < 100; ++$level) {
            $node['next'] = [];
            $node = &$node['next'];
        }

        unset($node);

        $errors = (new AiSchemaValidator())->validate($schema, $data);

        self::assertCount(1, $errors);
        self::assertStringEndsWith(': too deep', $errors[0]);
    }

    public function testTheReportIsBoundedAndNeverQuotesAValue(): void
    {
        $schema = ['type' => 'object', 'properties' => ['items' => ['type' => 'array', 'items' => ['type' => 'integer']]]];
        $errors = (new AiSchemaValidator())->validate($schema, ['items' => array_fill(0, 50, 'secret@example.com')]);

        self::assertCount(5, $errors);
        self::assertStringNotContainsString('secret', implode(' ', $errors));
    }
}
