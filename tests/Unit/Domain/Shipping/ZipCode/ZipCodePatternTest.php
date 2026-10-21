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
        $this->expectException(\InvalidArgumentException::class);

        ZipCodePattern::fromString($pattern);
    }

    public function testThePatternIsKeptInItsNormalForm(): void
    {
        self::assertSame('SW1A1AA', (string) ZipCodePattern::fromString(' sw1a 1aa '));
        self::assertSame('75001...75020', (string) ZipCodePattern::fromString('75 001...75-020'));
        self::assertSame('7*1', (string) ZipCodePattern::fromString('7***1'), 'One star per gap.');
    }

    public function testACodeThatIsNotValidUtf8HasNoNormalForm(): void
    {
        self::assertNull(ZipCodePattern::normalize("20\xC3\x28"));
        self::assertSame('', ZipCodePattern::normalize(' - '), 'A blank code is unknown, not unreadable.');
    }
}
