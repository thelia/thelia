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

namespace Thelia\Tests\Unit\Tools;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Tools\TerminalText;

final class TerminalTextTest extends TestCase
{
    #[DataProvider('texts')]
    public function testControlCharactersAreReplaced(string $text, string $expected): void
    {
        self::assertSame($expected, TerminalText::withoutControlCharacters($text));
    }

    /** @return iterable<string, array{string, string}> */
    public static function texts(): iterable
    {
        yield 'an escape sequence' => ["Acme\e[31m", 'Acme?[31m'];
        yield 'a carriage return' => ["Acme\rFake", 'Acme?Fake'];
        yield 'a C1 control sequence introducer' => ["Acme\u{9B}31m", 'Acme?31m'];
        yield 'a right-to-left override' => ["Acme\u{202E}eludoM", 'Acme?eludoM'];
        yield 'an isolate' => ["Acme\u{2066}Module\u{2069}", 'Acme?Module?'];
        yield 'a zero-width space' => ["Ac\u{200B}me", 'Ac?me'];
        yield 'a line separator' => ["Acme\u{2028}Fake", 'Acme?Fake'];
        yield 'a byte order mark' => ["\u{FEFF}Acme", '?Acme'];
        yield 'an operating system command introducer' => ["Acme\u{9D}8;;", 'Acme?8;;'];
        yield 'an arabic letter mark' => ["Acme\u{61C}Module", 'Acme?Module'];
        yield 'a word joiner' => ["Ac\u{2060}me", 'Ac?me'];
        yield 'an invisible separator' => ["Ac\u{2063}me", 'Ac?me'];
        yield 'a soft hyphen' => ["Ac\u{AD}me", 'Ac?me'];
        yield 'a deprecated format control' => ["Acme\u{206B}Module", 'Acme?Module'];
        yield 'an interlinear annotation anchor' => ["Acme\u{FFF9}Module", 'Acme?Module'];
        yield 'a tag character' => ["Acme\u{E0041}Module", 'Acme?Module'];
        yield 'the replacement character is kept' => ["Acme\u{FFFD}", "Acme\u{FFFD}"];
        yield 'an emoji is kept' => ["Acme \u{1F600}", "Acme \u{1F600}"];
        yield 'a no-break space is kept' => ["Acme\u{A0}Module", "Acme\u{A0}Module"];
        yield 'punctuation of the same block is kept' => ['Acme – Module…', 'Acme – Module…'];
        yield 'tabs and line feeds are kept' => ["Acme\n\tModule", "Acme\n\tModule"];
        yield 'accented letters are kept' => ['Modulé', 'Modulé'];
        yield 'invalid UTF-8 is cleaned, not dropped' => ["Acme\xFF\e", "Acme\xFF?"];
    }
}
