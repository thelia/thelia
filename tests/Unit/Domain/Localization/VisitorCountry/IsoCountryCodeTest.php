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

namespace Thelia\Tests\Unit\Domain\Localization\VisitorCountry;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Localization\VisitorCountry\IsoCountryCode;

final class IsoCountryCodeTest extends TestCase
{
    #[DataProvider('retained')]
    public function testTwoLettersAreNormalizedToUpperCase(string $raw, string $expected): void
    {
        self::assertSame($expected, IsoCountryCode::normalize($raw));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function retained(): iterable
    {
        yield 'already upper case' => ['BE', 'BE'];
        yield 'lower case' => ['be', 'BE'];
        yield 'mixed case' => ['bE', 'BE'];
        yield 'surrounding blanks' => [" fr\t", 'FR'];
    }

    #[DataProvider('refused')]
    public function testAnythingThatIsNotTwoLettersIsRefused(?string $raw): void
    {
        self::assertNull(IsoCountryCode::normalize($raw));
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function refused(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'markup' => ["B<'"];
        yield 'alpha-3' => ['BEL'];
        yield 'one letter' => ['B'];
        yield 'digits' => ['12'];
        yield 'a letter and a digit' => ['T1'];
        yield 'a trailing line break and more' => ["BE\nXX"];
        yield 'a null byte' => ["B\0"];
    }
}
