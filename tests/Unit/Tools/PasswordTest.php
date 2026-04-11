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

use PHPUnit\Framework\TestCase;
use Thelia\Tools\Password;

final class PasswordTest extends TestCase
{
    public function testGenerateRandomHasTheRequestedLength(): void
    {
        self::assertSame(8, \strlen(Password::generateRandom()));
        self::assertSame(1, \strlen(Password::generateRandom(1)));
        self::assertSame(42, \strlen(Password::generateRandom(42)));
    }

    public function testGenerateRandomOnlyContainsAlphanumericCharacters(): void
    {
        $password = Password::generateRandom(64);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $password);
    }

    public function testGenerateRandomProducesDifferentValuesOnSuccessiveCalls(): void
    {
        // Not strictly guaranteed for tiny lengths, but for length >= 16 the
        // collision probability is astronomically low: if this ever fails,
        // the entropy source is broken and that is worth knowing.
        $passwords = [];
        for ($i = 0; $i < 10; ++$i) {
            $passwords[] = Password::generateRandom(16);
        }

        self::assertCount(10, array_unique($passwords));
    }

    public function testGenerateHexaRandomOnlyContainsHexUpperAndDigits(): void
    {
        $hex = Password::generateHexaRandom(32);

        self::assertSame(32, \strlen($hex));
        self::assertMatchesRegularExpression('/^[A-F0-9]+$/', $hex);
    }
}
