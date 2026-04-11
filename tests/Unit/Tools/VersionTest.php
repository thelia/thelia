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
use Thelia\Tools\Version\Version;

final class VersionTest extends TestCase
{
    #[DataProvider('matchingConstraints')]
    public function testMatchingConstraints(string $version, string $constraints): void
    {
        self::assertTrue(Version::test($version, $constraints));
    }

    #[DataProvider('nonMatchingConstraints')]
    public function testNonMatchingConstraints(string $version, string $constraints): void
    {
        self::assertFalse(Version::test($version, $constraints));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function matchingConstraints(): iterable
    {
        yield 'exact equal' => ['2.4.0', '2.4.0'];
        yield 'exact equal with operator' => ['2.4.0', '=2.4.0'];
        yield 'greater or equal' => ['2.4.0', '>=2.4.0'];
        yield 'greater' => ['2.4.1', '>2.4.0'];
        yield 'lower or equal' => ['2.4.0', '<=2.4.0'];
        yield 'lower' => ['2.3.9', '<2.4.0'];
        yield 'tilde major minor' => ['2.4.2', '~2.4'];
        yield 'compound range' => ['2.4.3', '>=2.4.0 <=2.4.5'];
        yield 'compound tilde with cap' => ['2.4.2', '~2.4 <=2.4.4'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function nonMatchingConstraints(): iterable
    {
        yield 'not equal' => ['2.4.0', '2.4.1'];
        yield 'greater fails' => ['2.4.0', '>2.4.0'];
        yield 'lower fails' => ['2.4.0', '<2.4.0'];
        yield 'above upper bound' => ['2.5.0', '>=2.4.0 <=2.4.9'];
        yield 'below lower bound' => ['2.3.0', '>=2.4.0 <=2.4.9'];
        yield 'tilde major mismatch' => ['3.0.0', '~2.4'];
    }

    public function testParseBreaksDownASemanticVersion(): void
    {
        $parsed = Version::parse('2.4.3');

        self::assertSame('2.4.3', $parsed['version']);
        self::assertSame('2', $parsed['major']);
        self::assertSame('4', $parsed['minus']);
        self::assertSame('3', $parsed['release']);
        self::assertSame('', $parsed['extra']);
    }

    public function testParseCapturesExtraSuffix(): void
    {
        $parsed = Version::parse('2.5.0-alpha1');

        self::assertSame('2.5.0-alpha1', $parsed['version']);
        self::assertSame('alpha1', $parsed['extra']);
    }

    public function testParseRejectsInvalidVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Version::parse('not-a-version');
    }
}
