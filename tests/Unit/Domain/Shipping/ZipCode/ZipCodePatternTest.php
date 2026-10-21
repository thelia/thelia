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

namespace Thelia\Tests\Unit\Domain\Shipping\ZipCode;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Shipping\ZipCode\Exception\InvalidZipCodeRuleException;
use Thelia\Domain\Shipping\ZipCode\ZipCodePattern;

final class ZipCodePatternTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function codes(): iterable
    {
        yield 'exact code' => ['75001', '75001', true];
        yield 'another exact code' => ['75001', '75002', false];
        yield 'prefix of Corsica' => ['20*', '20090', true];
        yield 'prefix does not match elsewhere in the code' => ['20*', '75020', false];
        yield 'wildcard inside' => ['75*1', '75011', true];
        yield 'range lower bound' => ['75001...75020', '75001', true];
        yield 'range upper bound' => ['75001...75020', '75020', true];
        yield 'range inside' => ['75001...75020', '75011', true];
        yield 'range outside' => ['75001...75020', '75021', false];
        yield 'range against a code with letters' => ['1000...2000', '10A0', false];
        yield 'spaces and case' => ['SW1A 1AA', 'sw1a1aa', true];
        yield 'hyphen' => ['1000-001', '1000 001', true];
        yield 'prefix with letters' => ['sw1*', 'SW1A 1AA', true];
        yield 'empty code' => ['75*', '  ', false];
        yield 'a dot is not a wildcard' => ['7.001', '75001', false];
        yield 'range with a leading zero' => ['01000...01999', '01500', true];
        yield 'range against a shorter code' => ['01000...01999', '1500', false];
        yield 'range against a longer code' => ['01000...01999', '001500', false];
        yield 'range above its upper bound' => ['01000...01999', '02000', false];
        yield 'range of long codes' => ['12345678901234567890...12345678901234567899', '12345678901234567895', true];
        yield 'full-width digits' => ['20*', "\u{FF12}\u{FF10}090", true];
        yield 'a full-width pattern' => ["\u{FF17}\u{FF15}001", '75001', true];
        yield 'zero-width space' => ['20090', "20\u{200B}090", true];
        yield 'no-break space' => ['SW1A1AA', "SW1A\u{00A0}1AA", true];
        yield 'non-breaking hyphen' => ['1000-001', "1000\u{2011}001", true];
        yield 'an en dash' => ['1000-001', "1000\u{2013}001", true];
        yield 'a figure dash' => ['1000-001', "1000\u{2012}001", true];
        yield 'a horizontal bar' => ['1000-001', "1000\u{2015}001", true];
        yield 'a minus sign' => ['1000-001', "1000\u{2212}001", true];
        yield 'arabic-indic digits' => ['20*', "\u{0662}\u{0660}\u{0660}\u{0669}\u{0660}", true];
        yield 'devanagari digits in a range' => ['75001...75020', "\u{096D}\u{096B}\u{0966}\u{0967}\u{0966}", true];
        yield 'range of long codes, outside' => ['12345678901234567890...12345678901234567899', '12345678901234567900', false];
    }

    #[DataProvider('codes')]
    public function testAPatternMatchesTheCodesItNames(string $pattern, string $zipCode, bool $matches): void
    {
        self::assertSame($matches, ZipCodePattern::fromString($pattern)->matches($zipCode));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function patternsThatMatchNothing(): iterable
    {
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
        yield 'only a star' => ['*'];
        yield 'range with letters' => ['A1...B2'];
        yield 'range upside down' => ['75020...75001'];
        yield 'range with a star' => ['75*...76000'];
        yield 'range with bounds of two lengths' => ['1000...20000'];
        yield 'longer than the column' => [str_repeat('7', 51)];
    }

    #[DataProvider('patternsThatMatchNothing')]
    public function testAPatternThatCanMatchNothingIsRefused(string $pattern): void
    {
        $this->expectException(InvalidZipCodeRuleException::class);

        ZipCodePattern::fromString($pattern);
    }

    public function testThePatternIsKeptInItsNormalForm(): void
    {
        self::assertSame('SW1A1AA', (string) ZipCodePattern::fromString(' sw1a 1aa '));
        self::assertSame('75001...75020', (string) ZipCodePattern::fromString('75 001...75-020'));
        self::assertSame('7*1', (string) ZipCodePattern::fromString('7***1'), 'One star per gap.');
        self::assertSame('20*', (string) ZipCodePattern::fromString("\u{FF12}\u{FF10}\u{FF0A}"), 'Written in full-width characters, kept in its normal form.');
    }

    public function testACodeThatIsNotValidUtf8HasNoNormalForm(): void
    {
        self::assertNull(ZipCodePattern::normalize("20\xC3\x28"));
        self::assertSame('', ZipCodePattern::normalize(' - '), 'A blank code is unknown, not unreadable.');
    }

    public function testThePatternOfAnUnreadableRuleMatchesNothingEvenAnUncertainCode(): void
    {
        $nothing = ZipCodePattern::matchingNothing();

        self::assertFalse($nothing->matches('75001', whenUnsure: true));
        self::assertFalse($nothing->matches("20\xC3\x28", whenUnsure: true), 'An unreadable exclusion excludes nothing, not even a code that cannot be read.');
    }

    public function testTheTextOfARuleIsLoggedWithItsControlCharactersEscaped(): void
    {
        self::assertSame('20*\\r\\n[ERROR] forged\\000\\177', ZipCodePattern::forLog("20*\r\n[ERROR] forged\0\x7F"));
        self::assertSame('SW1A1AA', ZipCodePattern::forLog('SW1A1AA'));
    }

    /**
     * A removed character no longer keeps apart what composes: the normal form composes it,
     * and is the same applied twice.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function codesToNormalizeTwice(): iterable
    {
        yield 'a soft hyphen before a combining accent' => ["A\u{00AD}\u{0301}", "\u{00C1}"];
        yield 'a zero-width space before a combining accent' => ["E\u{200B}\u{0301}1", "\u{00C9}1"];
        yield 'full-width digits' => ["\u{FF12}\u{FF10} 090", '20090'];
    }

    #[DataProvider('codesToNormalizeTwice')]
    public function testTheNormalFormIsTheSameAppliedTwice(string $zipCode, string $normalForm): void
    {
        self::assertSame($normalForm, ZipCodePattern::normalize($zipCode));
        self::assertSame($normalForm, ZipCodePattern::normalize($normalForm));
    }

    public function testThePatternLengthIsCountedInCharacters(): void
    {
        self::assertSame(str_repeat("\u{00C9}", 26), (string) ZipCodePattern::fromString(str_repeat("\u{00C9}", 26)), '26 accented letters are 52 bytes and 26 characters.');

        $this->expectException(InvalidZipCodeRuleException::class);
        ZipCodePattern::fromString(str_repeat("\u{00C9}", 51));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function textsForTheLog(): iterable
    {
        yield 'a backslash' => ['20\\*', '20\\\\*'];
        yield 'a next line' => ["20\u{0085}*", '20\\u{0085}*'];
        yield 'a line separator' => ["20\u{2028}*", '20\\u{2028}*'];
        yield 'a paragraph separator' => ["20\u{2029}*", '20\\u{2029}*'];
        yield 'a right-to-left override' => ["20\u{202E}*", '20\\u{202E}*'];
        yield 'a first strong isolate' => ["20\u{2068}*", '20\\u{2068}*'];
        yield 'a text that is not UTF-8' => ["20\xC3\x28", '20\\303('];
    }

    #[DataProvider('textsForTheLog')]
    public function testATextIsLoggedWithWhatCouldForgeOrTurnALineEscaped(string $text, string $logged): void
    {
        self::assertSame($logged, ZipCodePattern::forLog($text));
    }
}
