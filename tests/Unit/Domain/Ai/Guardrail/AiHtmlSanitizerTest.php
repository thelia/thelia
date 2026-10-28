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
use Thelia\Domain\Ai\Guardrail\AiHtmlSanitizer;

final class AiHtmlSanitizerTest extends TestCase
{
    /**
     * @param list<string> $absent what must not survive
     */
    #[DataProvider('hostileHtml')]
    public function testTheProfileRemovesWhatCanRunOrLoadCode(string $html, array $absent): void
    {
        $clean = (new AiHtmlSanitizer())->sanitize($html);

        foreach ($absent as $forbidden) {
            self::assertStringNotContainsStringIgnoringCase($forbidden, $clean);
        }
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function hostileHtml(): iterable
    {
        yield 'a script, with its content' => ['<p>Hi</p><script>alert(1)</script>', ['<script', 'alert']];
        yield 'a style element, with its content' => ['<p>Hi</p><style>body{display:none}</style>', ['<style', 'display']];
        yield 'an iframe' => ['<iframe src="https://evil.example"></iframe><p>Hi</p>', ['iframe', 'evil.example']];
        yield 'an object and an embed' => ['<object data="x.swf"></object><embed src="x.swf">', ['<object', '<embed', 'x.swf']];
        yield 'a form' => ['<form action="https://evil.example"><input name="card"></form>', ['<form', 'evil.example']];
        yield 'an event attribute' => ['<p onclick="alert(1)">Hi</p><img src="https://a.example/a.png" onerror="alert(1)">', ['onclick', 'onerror', 'alert']];
        yield 'a style attribute' => ['<p style="position:fixed;inset:0">Hi</p>', ['style=']];
        yield 'a javascript link' => ['<a href="javascript:alert(1)">x</a>', ['javascript:']];
        yield 'an obfuscated javascript link' => ['<a href="  JaVa&#x09;Script:alert(1)">x</a>', ['script:alert']];
        yield 'a data link' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', ['data:']];
        yield 'a data image' => ['<img src="data:image/svg+xml;base64,PHN2Zz4=">', ['data:']];
        yield 'an ftp link' => ['<a href="ftp://files.example/a">x</a>', ['ftp:']];
    }

    public function testTheContentThatIsSafeSurvives(): void
    {
        $html = '<h2>Title</h2><p>A <strong>bold</strong> word, a <a href="https://example.com/a">link</a>, <a href="mailto:shop@example.com">mail</a> and <a href="/category/chairs">a relative one</a>.</p><ul><li>One</li></ul><img src="https://example.com/a.png" alt="A chair">';

        $clean = (new AiHtmlSanitizer())->sanitize($html);

        foreach (['<h2>Title</h2>', '<strong>bold</strong>', 'href="https://example.com/a"', 'href="mailto:shop&#64;example.com"', 'href="/category/chairs"', '<li>One</li>', 'src="https://example.com/a.png"', 'alt="A chair"'] as $kept) {
            self::assertStringContainsString($kept, $clean);
        }
    }

    public function testEveryLinkOpensWithoutAccessToThePageThatOpenedIt(): void
    {
        $clean = (new AiHtmlSanitizer())->sanitize('<a href="https://example.com" target="_blank" rel="opener">x</a>');

        self::assertStringContainsString('rel="noopener noreferrer"', $clean);
    }

    public function testALongPageIsNotCutShort(): void
    {
        $html = str_repeat('<p>0123456789</p>', 5000);

        self::assertSame(5000, substr_count((new AiHtmlSanitizer())->sanitize($html), '<p>'));
    }

    public function testOnlyTheFieldsTheSchemaMarksAsHtmlAreSanitized(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'body' => ['type' => 'string', 'contentMediaType' => 'text/html'],
                'sections' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'html' => ['type' => 'string', 'contentMediaType' => 'text/html'],
                    'note' => ['type' => 'string'],
                    'nested' => ['$ref' => '#/$defs/box'],
                ]]],
            ],
            '$defs' => ['box' => ['type' => 'object', 'additionalProperties' => ['type' => 'string', 'contentMediaType' => 'text/html']]],
        ];
        $evil = '<p>ok</p><script>x()</script>';
        $data = [
            'title' => $evil,
            'body' => $evil,
            'sections' => [['html' => $evil, 'note' => $evil, 'nested' => ['left' => $evil, 'right' => $evil]]],
        ];

        $clean = (new AiHtmlSanitizer())->sanitizeData($schema, $data);

        self::assertIsArray($clean);
        self::assertSame($evil, $clean['title']);
        self::assertSame('<p>ok</p>', $clean['body']);
        self::assertSame($evil, $clean['sections'][0]['note']);
        self::assertSame('<p>ok</p>', $clean['sections'][0]['html']);
        self::assertSame(['left' => '<p>ok</p>', 'right' => '<p>ok</p>'], $clean['sections'][0]['nested']);
    }

    public function testABranchOfAnyOfThatMarksAFieldIsApplied(): void
    {
        $schema = ['type' => 'object', 'properties' => ['content' => ['anyOf' => [['type' => 'null'], ['type' => 'string', 'contentMediaType' => 'text/html']]]]];

        $clean = (new AiHtmlSanitizer())->sanitizeData($schema, ['content' => '<b onmouseover="x()">hi</b>']);

        self::assertSame(['content' => '<b>hi</b>'], $clean);
        self::assertSame(['content' => null], (new AiHtmlSanitizer())->sanitizeData($schema, ['content' => null]));
    }

    public function testASchemaWithoutAnHtmlFieldSaysSo(): void
    {
        $sanitizer = new AiHtmlSanitizer();

        self::assertFalse($sanitizer->hasHtmlFields(['type' => 'object', 'properties' => ['a' => ['type' => 'string', 'contentMediaType' => 'text/plain']]]));
        self::assertTrue($sanitizer->hasHtmlFields(['type' => 'object', 'properties' => ['a' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['b' => ['contentMediaType' => 'text/html']]]]]]));
    }
}
