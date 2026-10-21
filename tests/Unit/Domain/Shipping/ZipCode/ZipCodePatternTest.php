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
        yield 'a dot, which is no wildcard' => ['7.001'];
        yield 'a letter outside A to Z' => ["\u{00C9}1*"];
        yield 'a variation selector' => ["20\u{FE0F}*"];
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function codesWithoutANormalForm(): iterable
    {
        yield 'not valid UTF-8' => ["20\xC3\x28"];
        yield 'a variation selector alone' => ["\u{FE0F}"];
        yield 'a variation selector inside' => ["2\u{FE0F}0000"];
        yield 'a zero-width space alone' => ["\u{200B}"];
        yield 'a C1 control' => ["20\u{0090}000"];
        yield 'a combining grapheme joiner' => ["20\u{034F}000"];
        yield 'a Mongolian variation selector' => ["20\u{180B}000"];
        yield 'a Hangul filler' => ["20\u{3164}000"];
        yield 'a braille blank' => ["20\u{2800}000"];
        yield 'a letter outside A to Z' => ["\u{00E9}1"];
        yield 'a combining accent' => ["E\u{0301}1"];
    }

    /**
     * Fail closed: a code that keeps a character outside A to Z and 0 to 9 is uncertain.
     */
    #[DataProvider('codesWithoutANormalForm')]
    public function testACodeThatKeepsAnotherCharacterHasNoNormalForm(string $zipCode): void
    {
        self::assertNull(ZipCodePattern::normalize($zipCode));
        self::assertFalse(ZipCodePattern::isUnknown($zipCode), 'It is not unknown either.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownCodes(): iterable
    {
        yield 'a hyphen' => ['-'];
        yield 'an en dash' => ["\u{2013}"];
        yield 'a hyphen between spaces' => [' - '];
        yield 'a minus sign' => ["\u{2212}"];
        yield 'spaces' => [" \t\u{00A0}"];
    }

    /**
     * A buyer who types "-" means "no code": the code is unknown, as an empty one.
     */
    #[DataProvider('unknownCodes')]
    public function testADashOrSpacesAloneIsAnUnknownCode(string $zipCode): void
    {
        self::assertTrue(ZipCodePattern::isUnknown($zipCode));
        self::assertNull(ZipCodePattern::nameable($zipCode), 'An unknown code is not named.');
    }

    public function testOnlyAKnownCodeWithANormalFormIsNamed(): void
    {
        self::assertSame('20 090', ZipCodePattern::nameable('20 090'));
        self::assertNull(ZipCodePattern::nameable("\u{200B}"));
        self::assertNull(ZipCodePattern::nameable("2\u{FE0F}0000"));
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
        yield 'a soft hyphen' => ["20\u{00AD}090", '20090'];
        yield 'a zero-width space between letters' => ["sw1a\u{200B}1aa", 'SW1A1AA'];
        yield 'full-width digits' => ["\u{FF12}\u{FF10} 090", '20090'];
        yield 'a two-em dash' => ["20\u{2E3A}000", '20000'];
        yield 'a wave dash' => ["20\u{301C}000", '20000'];
    }

    #[DataProvider('codesToNormalizeTwice')]
    public function testTheNormalFormIsTheSameAppliedTwice(string $zipCode, string $normalForm): void
    {
        self::assertSame($normalForm, ZipCodePattern::normalize($zipCode));
        self::assertSame($normalForm, ZipCodePattern::normalize($normalForm));
    }

    public function testAPatternIsRefusedPastTheLengthOfItsColumn(): void
    {
        self::assertSame(str_repeat('7', ZipCodePattern::MAX_LENGTH), (string) ZipCodePattern::fromString(str_repeat('7', ZipCodePattern::MAX_LENGTH)));

        $this->expectException(InvalidZipCodeRuleException::class);
        $this->expectExceptionMessage(\sprintf('A postal code pattern is %d characters long at most.', ZipCodePattern::MAX_LENGTH));
        ZipCodePattern::fromString(str_repeat('7', ZipCodePattern::MAX_LENGTH + 1));
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
        yield 'a C1 control sequence introducer' => ["20\u{009B}*", '20\\u{009B}*'];
        yield 'a left-to-right mark' => ["20\u{200E}*", '20\\u{200E}*'];
        yield 'a right-to-left mark' => ["20\u{200F}*", '20\\u{200F}*'];
        yield 'an Arabic letter mark' => ["20\u{061C}*", '20\\u{061C}*'];
    }

    #[DataProvider('textsForTheLog')]
    public function testATextIsLoggedWithWhatCouldForgeOrTurnALineEscaped(string $text, string $logged): void
    {
        self::assertSame($logged, ZipCodePattern::forLog($text));
    }
}
