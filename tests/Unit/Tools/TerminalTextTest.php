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
    public function testTheTextIsCleanedAsExpected(string $text, string $expected): void
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
        // A control character is shown as such.
        self::assertSame('| ??', TerminalText::onOneLine("|\n\f\x88"));
        // The result is the same the second time.
        self::assertSame(TerminalText::onOneLine("p\x88M\n\f"), TerminalText::onOneLine(TerminalText::onOneLine("p\x88M\n\f")));
        // A tab in a run of blanks without a line break is shown, at either end too; one in a
        // run that holds a line break goes with it.
        self::assertSame('?a?', TerminalText::onOneLine("\ta\t"));
        self::assertSame('a', TerminalText::onOneLine("\t\na\n\t"));
        self::assertSame('a', TerminalText::onOneLine("\t \na"));
        // A lone carriage return is a line break, never a return to the start of the line.
        self::assertSame('Acme OK', TerminalText::onOneLine("Acme\rOK"));
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

    /**
     * The blanks are read in Unicode mode, whatever the locale of the process: the second
     * byte of "à" (0xA0) is a blank to the tables of a C.UTF-8 locale on a libc whose
     * isspace() says so (macOS; glibc says no, so this test tells nothing on Linux).
     */
    public function testAnAccentBeforeALineBreakIsKeptWhateverTheLocale(): void
    {
        $locale = setlocale(\LC_CTYPE, '0');
        $tried = 0;

        try {
            foreach (['C.UTF-8', 'en_US.UTF-8'] as $candidate) {
                if (false === setlocale(\LC_CTYPE, $candidate)) {
                    continue;
                }

                ++$tried;
                self::assertSame("caf\u{E0} x \u{420}", TerminalText::onOneLine("caf\u{E0}\nx\n\u{420}"), $candidate);
            }
        } finally {
            setlocale(\LC_CTYPE, (string) $locale);
        }

        if (0 === $tried) {
            self::markTestSkipped('No locale to try.');
        }
    }

    /**
     * A run of thousands of blanks (a value libxml cites from a descriptor) is read once,
     * even without the PCRE JIT, the slower path: a pattern that looked for a line break
     * after the blanks was tried again from each blank of the run: five seconds on thirty
     * thousand (measured), and the time grows with the square of the run. In a process of
     * its own, as PCRE keeps a pattern compiled once, with the JIT it had.
     */
    #[RunInSeparateProcess]
    public function testALongRunOfBlanksIsReadOnce(): void
    {
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
     * Under a backtrack limit, with the JIT of PCRE off (on, no limit but the lowest stops
     * anything), the text comes out cleaned or not at all, never as it was. Which step a
     * given limit stops depends on the build of PCRE: the lowest refuses even the check
     * that the text is UTF-8, so the first step runs and fails on a valid text too; the
     * next ones let the check pass and stop the second step (limits 2 to 9, measured on
     * PCRE 10.46 and 10.47); none stops the third step or the run of blanks without
     * stopping an earlier step first. So the limits are swept, each result is held to the
     * two outcomes allowed, and the test is only worth something once a limit has let the
     * check pass and given nothing: it says so, or skips. What it proves is the fallback of
     * the second step, through the text that carries a character the second step alone
     * replaces (U+034F): a fallback giving the text back would show it. The other two
     * texts, one invalid and one that only the third step cleans (U+0600), hold the shape
     * of the outcome, nothing more.
     */
    #[RunInSeparateProcess]
    public function testABacktrackLimitLeavesNothingOrTheCleanedText(): void
    {
        $jit = \ini_get('pcre.jit');
        $limit = \ini_get('pcre.backtrack_limit');
        ini_set('pcre.jit', '0');
        $secondStepOnly = str_repeat("a\u{34F}", 2000);
        $texts = [
            [str_repeat("\xFFa\u{200B}", 2000), str_repeat('?a?', 2000)],
            [$secondStepOnly, str_repeat('a?', 2000)],
            [str_repeat("a\u{600}", 2000), str_repeat('a?', 2000)],
        ];
        $lines = str_repeat("a\n b", 2000);
        $secondStepStopped = 0;

        try {
            foreach (range(1, 32) as $backtrackLimit) {
                ini_set('pcre.backtrack_limit', (string) $backtrackLimit);
                $checkPasses = 1 === preg_match('//u', $secondStepOnly);

                foreach ($texts as [$text, $cleaned]) {
                    $result = TerminalText::withoutControlCharacters($text);
                    self::assertContains($result, ['', $cleaned], \sprintf('limit %d', $backtrackLimit));
                    $secondStepStopped += $checkPasses && $text === $secondStepOnly && '' === $result ? 1 : 0;
                }

                self::assertContains(TerminalText::onOneLine($lines), ['', str_repeat('a b', 2000)], \sprintf('limit %d', $backtrackLimit));
            }
        } finally {
            ini_set('pcre.jit', (string) $jit);
            ini_set('pcre.backtrack_limit', (string) $limit);
        }

        if (0 === $secondStepStopped) {
            self::markTestSkipped('No limit from 1 to 32 lets the UTF-8 check pass and stops the second step on this build of PCRE: the fallback of the second step is not proven here.');
        }
    }

    /**
     * Every point of each range replaced is replaced, and the neighbours of the range kept,
     * unless the neighbour is itself in a range, or a control, format or private use
     * character. What it proves: a range narrowed by one code point, or holed, is seen
     * (U+180C among the Mongolian selectors, say). What it cannot prove: a narrowed or
     * holed range of the second step that the third step covers too (the format characters,
     * the Egyptian hieroglyph controls on a PCRE with the tables of Unicode 15, and the tags
     * and variation selectors of plane 14 among them), as the third step stands behind it.
     * The one range of plane 14, U+E0000 to U+E0FFF, is for that reason only sampled, one
     * point in sixty-four and its last.
     */
    #[DataProvider('ranges')]
    public function testEveryPointOfARangeIsReplacedAndItsNeighboursKept(int $first, int $last): void
    {
        $step = $last - $first > 1024 ? 64 : 1;

        for ($codePoint = $first; $codePoint <= $last; $codePoint += $step) {
            self::assertSame('a?b', TerminalText::withoutControlCharacters('a'.mb_chr($codePoint).'b'), \sprintf('U+%04X', $codePoint));
        }

        if (0 !== ($last - $first) % $step) {
            self::assertSame('a?b', TerminalText::withoutControlCharacters('a'.mb_chr($last).'b'), \sprintf('U+%04X', $last));
        }

        foreach ([$first - 1, $last + 1] as $neighbour) {
            if (!self::isACodePoint($neighbour) || self::isInAReplacedRangeOrCategory($neighbour)) {
                continue;
            }

            self::assertSame('a'.mb_chr($neighbour).'b', TerminalText::withoutControlCharacters('a'.mb_chr($neighbour).'b'), \sprintf('U+%04X', $neighbour));
        }
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
        yield 'an overlong four-byte sequence' => ["Acme\xF0\x8F\xBF\xBF", 'Acme????'];
        yield 'a lead byte of an overlong two-byte sequence' => ["Acme\xC1\xBF", 'Acme??'];
        yield 'a lead byte beyond UTF-8' => ["Acme\xF5\x80\x80\x80", 'Acme????'];
        // A text with an invalid byte goes through the first step: the valid characters at
        // the bounds of each sequence length stay whole there.
        yield 'U+0800, the first three-byte character, next to an invalid byte' => ["\xFF\u{800}", "?\u{800}"];
        yield 'U+D7FF, the last before the surrogates, next to an invalid byte' => ["\xFF\u{D7FF}", "?\u{D7FF}"];
        yield 'U+10000, the first four-byte character, next to an invalid byte' => ["\xFF\u{10000}", "?\u{10000}"];
        yield 'U+3FFFD, an unassigned four-byte character, next to an invalid byte' => ["\xFF\u{3FFFD}", "?\u{3FFFD}"];
        yield 'U+10FFFD, the last private use character, next to an invalid byte' => ["\xFF\u{10FFFD}", '??'];
        yield 'U+FFFD, the replacement character, next to an invalid byte' => ["\xFF\u{FFFD}", "?\u{FFFD}"];
        yield 'U+07FF, the last two-byte character, next to an invalid byte' => ["\xFF\u{7FF}", "?\u{7FF}"];
        yield 'U+1000, the first character of the E1 lead byte, next to an invalid byte' => ["\xFF\u{1000}", "?\u{1000}"];
        yield 'U+CFFF, the last character of the EC lead byte, next to an invalid byte' => ["\xFF\u{CFFF}", "?\u{CFFF}"];
        yield 'U+E000, private use behind the EE lead byte, next to an invalid byte' => ["\xFF\u{E000}", '??'];
        yield 'U+40000, the first character of the F1 lead byte, next to an invalid byte' => ["\xFF\u{40000}", "?\u{40000}"];
        yield 'U+FFFFF, a noncharacter behind the F3 lead byte, next to an invalid byte' => ["\xFF\u{FFFFF}", '??'];
        yield 'U+00A1, a character of the C2 lead byte that is kept, next to an invalid byte' => ["\xFF\u{A1}", "?\u{A1}"];
        yield 'U+0FFF, the last second byte of E0, next to an invalid byte' => ["\xFF\u{FFF}", "?\u{FFF}"];
        yield 'U+D000, the first second byte of ED, next to an invalid byte' => ["\xFF\u{D000}", "?\u{D000}"];
        yield 'U+80000, behind the F2 lead byte, next to an invalid byte' => ["\xFF\u{80000}", "?\u{80000}"];
        yield 'U+100000, the first second byte of F4 (private use), next to an invalid byte' => ["\xFF\u{100000}", '??'];
        yield 'an Egyptian hieroglyph format control' => ["Acme\u{13437}", 'Acme?'];
        yield 'the hieroglyph after that range is kept' => ["Acme\u{13440}", "Acme\u{13440}"];
        yield 'an unassigned ignorable of the specials block' => ["Acme\u{FFF0}", 'Acme?'];
        // The bounds of every range, and their neighbours, are the bounds test's: here, a
        // character in its context (a word, a sequence, an identifier), and the inner points
        // of the ranges the docblock splits.
        yield 'the last bidirectional isolate (U+2069, inside U+2066 to U+206F)' => ["Ac\u{2069}me", 'Ac?me'];
        yield 'the paragraph separator (U+2029, inside U+2028 to U+202E)' => ["Ac\u{2029}me", 'Ac?me'];
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
            [0xFFF9, 0xFFFB], [0xFFFC, 0xFFFC], [0x13430, 0x1343F], [0x1BCA0, 0x1BCA3], [0x1D173, 0x1D17A],
            [0xE0000, 0xE0FFF],
        ];

        for ($plane = 0; $plane <= 16; ++$plane) {
            $ranges[] = [$plane * 0x10000 + 0xFFFE, $plane * 0x10000 + 0xFFFF];
        }

        return $ranges;
    }

    private static function isACodePoint(int $value): bool
    {
        return $value >= 0 && $value <= 0x10FFFF && ($value < 0xD800 || $value > 0xDFFF);
    }

    private static function isInAReplacedRangeOrCategory(int $codePoint): bool
    {
        foreach (self::replacedRanges() as [$first, $last]) {
            if ($codePoint >= $first && $codePoint <= $last) {
                return true;
            }
        }

        return 1 === preg_match('/[\p{Cc}\p{Cf}\p{Co}]/u', mb_chr($codePoint));
    }
}
