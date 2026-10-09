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
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Thelia\Tools\TerminalText;

final class TerminalTextTest extends TestCase
{
    #[DataProvider('texts')]
    public function testControlCharactersAreReplaced(string $text, string $expected): void
    {
        self::assertSame($expected, TerminalText::withoutControlCharacters($text));
    }

    public function testAnIdentifierStaysOnOneLine(): void
    {
        self::assertSame("Acme?OK: install complete?\u{A0}", TerminalText::singleLine("Acme\nOK: install complete\t\u{A0}"));
        self::assertSame('Acme?', TerminalText::singleLine("Acme\e"));
    }

    public function testAMessageSpreadOverLinesIsPrintedOnOne(): void
    {
        self::assertSame('first error. second error.?OK', TerminalText::onOneLine("first error.\n  \r\nsecond error.\tOK\n"));
        // A control character is shown as such, and the result is the same the second time.
        self::assertSame('| ??', TerminalText::onOneLine("|\n\f\x88"));
        self::assertSame(TerminalText::onOneLine("p\x88M\n\f"), TerminalText::onOneLine(TerminalText::onOneLine("p\x88M\n\f")));
        // A lone carriage return is a line break, never a return to the start of the line.
        self::assertSame('Acme OK', TerminalText::onOneLine("Acme\rOK"));
    }

    /**
     * The blanks are read in Unicode mode, whatever the locale of the process: the second
     * byte of "à" (0xA0) is a blank to the tables of a C.UTF-8 locale on a libc whose
     * isspace() says so (macOS; glibc says no, so this test tells nothing on Linux).
     */
    public function testAnAccentBeforeALineBreakIsKeptWhateverTheLocale(): void
    {
        $locale = setlocale(\LC_CTYPE, '0');

        try {
            foreach (['C.UTF-8', 'C'] as $candidate) {
                if (false === setlocale(\LC_CTYPE, $candidate)) {
                    continue;
                }

                self::assertSame("caf\u{E0} x \u{420}", TerminalText::onOneLine("caf\u{E0}\nx\n\u{420}"), $candidate);
            }
        } finally {
            setlocale(\LC_CTYPE, (string) $locale);
        }
    }

    /**
     * A run of thousands of blanks (a value libxml cites from a descriptor) is read once,
     * with or without the PCRE JIT: a pattern that looked for a line break after the blanks
     * was tried again from each blank of the run, and took 17 seconds on fifty thousand.
     */
    #[RunInSeparateProcess]
    public function testALongRunOfBlanksIsReadOnce(): void
    {
        // In a process of its own: PCRE keeps a pattern compiled once, with the JIT it had.
        // A pattern tried again from each blank of the run takes minutes on this one.
        $text = 'a'.str_repeat(' ', 200000).'b'.str_repeat("\t", 100000).'c'.str_repeat("\n ", 50000).'d';
        $jit = \ini_get('pcre.jit');
        ini_set('pcre.jit', '0');
        $started = hrtime(true);

        try {
            $printed = TerminalText::onOneLine($text);
        } finally {
            ini_set('pcre.jit', (string) $jit);
        }

        self::assertSame('a'.str_repeat(' ', 200000).'b'.str_repeat('?', 100000).'c d', $printed);
        self::assertLessThan(2_000_000_000, hrtime(true) - $started);
    }

    /**
     * The bounds of every range replaced are replaced, and their neighbours kept, unless the
     * neighbour is itself in a range, or a control, format or private use character: a range
     * narrowed by one code point is seen, but for the ranges of the second step that the
     * third step covers too (the format characters among them), which stand as a backstop.
     */
    #[DataProvider('ranges')]
    public function testTheBoundsOfARangeAreReplacedAndItsNeighboursKept(int $first, int $last): void
    {
        foreach ([$first, $last] as $bound) {
            self::assertSame('a?b', TerminalText::withoutControlCharacters('a'.mb_chr($bound).'b'), \sprintf('U+%04X', $bound));
        }

        foreach ([$first - 1, $last + 1] as $neighbour) {
            if ($neighbour < 0 || $neighbour > 0x10FFFF || ($neighbour >= 0xD800 && $neighbour <= 0xDFFF) || self::isReplacedByItself($neighbour)) {
                continue;
            }

            self::assertSame('a'.mb_chr($neighbour).'b', TerminalText::withoutControlCharacters('a'.mb_chr($neighbour).'b'), \sprintf('U+%04X', $neighbour));
        }
    }

    /**
     * The blanks are those of Unicode, not of ASCII alone: an ideographic space or a
     * no-break space next to a line break goes with it, and stays on its own.
     */
    public function testABlankOfUnicodeAroundALineBreakGoesWithIt(): void
    {
        self::assertSame('a b', TerminalText::onOneLine("a\u{3000}\n\u{A0}b"));
        self::assertSame("a\u{3000}b", TerminalText::onOneLine("a\u{3000}b"));
        self::assertSame("a\u{A0}\u{A0}b", TerminalText::onOneLine("a\u{A0}\u{A0}b"));
        // At either end, only the ASCII blanks go; a blank of Unicode next to a line break goes with it.
        self::assertSame("\u{A0}a\u{3000}", TerminalText::onOneLine(" \u{A0}a\u{3000} "));
        self::assertSame('a', TerminalText::onOneLine(" \n\u{A0}a\u{3000} \n"));
    }

    /** @return iterable<string, array{int, int}> */
    public static function ranges(): iterable
    {
        foreach (self::replacedRanges() as [$first, $last]) {
            yield \sprintf('U+%04X to U+%04X', $first, $last) => [$first, $last];
        }
    }

    /**
     * The ranges the docblock of withoutControlCharacters() lists, and the two noncharacters
     * of each of the 17 planes.
     *
     * @return list<array{int, int}>
     */
    private static function replacedRanges(): array
    {
        $ranges = [
            [0x00, 0x08], [0x0B, 0x1F], [0x7F, 0x7F], [0x80, 0x9F], [0xAD, 0xAD], [0x34F, 0x34F], [0x61C, 0x61C],
            [0x115F, 0x1160], [0x17B4, 0x17B5], [0x180B, 0x180F], [0x200B, 0x200F], [0x2028, 0x202E],
            [0x2060, 0x2064], [0x2065, 0x2065], [0x2066, 0x206F], [0x2800, 0x2800], [0x3164, 0x3164],
            [0xFDD0, 0xFDEF], [0xFE00, 0xFE0F], [0xFEFF, 0xFEFF], [0xFFA0, 0xFFA0], [0xFFF0, 0xFFF8],
            [0xFFF9, 0xFFFB], [0xFFFC, 0xFFFC], [0x1BCA0, 0x1BCA3], [0x1D173, 0x1D17A], [0xE0000, 0xE0FFF],
        ];

        for ($plane = 0; $plane <= 16; ++$plane) {
            $ranges[] = [$plane * 0x10000 + 0xFFFE, $plane * 0x10000 + 0xFFFF];
        }

        return $ranges;
    }

    private static function isReplacedByItself(int $codePoint): bool
    {
        foreach (self::replacedRanges() as [$first, $last]) {
            if ($codePoint >= $first && $codePoint <= $last) {
                return true;
            }
        }

        return 1 === preg_match('/[\p{Cc}\p{Cf}\p{Co}]/u', mb_chr($codePoint));
    }

    /** @return iterable<string, array{string, string}> */
    public static function texts(): iterable
    {
        yield 'an escape sequence' => ["Acme\e[31m", 'Acme?[31m'];
        yield 'a carriage return' => ["Acme\rFake", 'Acme?Fake'];
        yield 'a C1 control sequence introducer' => ["Acme\u{9B}31m", 'Acme?31m'];
        yield 'a format character off the list (Arabic number sign)' => ["Acme\u{600}1", 'Acme?1'];
        yield 'the object replacement character' => ["Acme\u{FFFC}", 'Acme?'];
        yield 'the unassigned ignorable U+2065' => ["Ac\u{2065}me", 'Ac?me'];
        yield 'a private use character' => ["Acme\u{E000}", 'Acme?'];
        yield 'a letter with an accent is kept' => ['Acmé', 'Acmé'];
        yield 'a noncharacter' => ["Acme\u{FFFE}", 'Acme?'];
        yield 'a noncharacter of the first supplementary plane' => ["Acme\u{1FFFE}", 'Acme?'];
        yield 'a noncharacter of the last plane' => ["Acme\u{10FFFF}", 'Acme?'];
        yield 'a noncharacter of the Arabic presentation block' => ["Acme\u{FDD0}", 'Acme?'];
        yield 'an unassigned code point of the tags plane' => ["Acme\u{E0200}", 'Acme?'];
        yield 'a format character of the Syriac block' => ["Acme\u{70F}", 'Acme?'];
        yield 'a format character of a supplementary plane' => ["Acme\u{110BD}", 'Acme?'];
        yield 'a private use character of plane 15' => ["Acme\u{F0000}", 'Acme?'];
        yield 'a private use character of plane 16' => ["Acme\u{10FFFD}", 'Acme?'];
        yield 'a sequence beyond U+10FFFF' => ["Acme\xF4\x90\x80\x80", 'Acme????'];
        yield 'an overlong sequence' => ["Acme\xC0\x80", 'Acme??'];
        yield 'an overlong three-byte sequence' => ["Acme\xE0\x80\x80", 'Acme???'];
        yield 'an unassigned ignorable of the specials block' => ["Acme\u{FFF0}", 'Acme?'];
        yield 'the last bidirectional isolate (bound of a range)' => ["Ac\u{2069}me", 'Ac?me'];
        yield 'the narrow no-break space next to that range is kept' => ["Ac\u{202F}me", "Ac\u{202F}me"];
        yield 'the paragraph separator (bound of a range)' => ["Ac\u{2029}me", 'Ac?me'];
        yield 'the hair space next to the zero-width range is kept' => ["Ac\u{200A}me", "Ac\u{200A}me"];
        yield 'the first Hangul filler (bound of a range)' => ["Ac\u{115F}me", 'Ac?me'];
        yield 'the Hangul letter before it is kept' => ["Ac\u{115E}me", "Ac\u{115E}me"];
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
        yield 'a mongolian vowel separator' => ["Ac\u{180E}me", 'Ac?me'];
        yield 'a combining grapheme joiner' => ["Ac\u{34F}me", 'Ac?me'];
        yield 'a variation selector' => ["Acme\u{FE0F}", 'Acme?'];
        yield 'a hangul filler' => ["Acme\u{3164}Module", 'Acme?Module'];
        yield 'a braille blank' => ["Acme\u{2800}Module", 'Acme?Module'];
        yield 'a braille dot is kept' => ["Acme\u{2801}", "Acme\u{2801}"];
        yield 'the replacement character is kept' => ["Acme\u{FFFD}", "Acme\u{FFFD}"];
        yield 'an emoji is kept' => ["Acme \u{1F600}", "Acme \u{1F600}"];
        yield 'a no-break space is kept' => ["Acme\u{A0}Module", "Acme\u{A0}Module"];
        yield 'punctuation of the same block is kept' => ['Acme – Module…', 'Acme – Module…'];
        yield 'tabs and line feeds are kept' => ["Acme\n\tModule", "Acme\n\tModule"];
        yield 'accented letters are kept' => ['Modulé', 'Modulé'];
        yield 'invalid UTF-8 is cleaned, not dropped' => ["Acme\xFF\e", 'Acme??'];
        yield 'a raw C1 byte' => ["Acme\x9B31m", 'Acme?31m'];
        yield 'a raw C1 byte after an accented letter' => ["Modulé\x9B31m", 'Modulé?31m'];
        yield 'a truncated sequence is replaced byte by byte' => ["Acme\xE2\x80Module", 'Acme??Module'];
        yield 'an encoded surrogate is replaced' => ["Acme\xED\xA0\x80", 'Acme???'];
        yield 'a mongolian free variation selector' => ["Ac\u{180B}me", 'Ac?me'];
        yield 'a supplementary variation selector' => ["Acme\u{E0100}", 'Acme?'];
        yield 'a musical format control' => ["Ac\u{1D173}me", 'Ac?me'];
        yield 'a khmer inherent vowel' => ["Ac\u{17B4}me", 'Ac?me'];
        yield 'a shorthand format control' => ["Ac\u{1BCA0}me", 'Ac?me'];
        yield 'an unassigned tag-block character' => ["Acme\u{E00FF}", 'Acme?'];
        yield 'a khmer vowel sign is kept' => ["Ac\u{17B6}me", "Ac\u{17B6}me"];
    }
}
