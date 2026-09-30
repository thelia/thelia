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
    private const string COST_VARIABLE = 'THELIA_PASSWORD_HASH_COST';

    private mixed $serverCost;

    private mixed $envCost;

    protected function setUp(): void
    {
        $this->serverCost = $_SERVER[self::COST_VARIABLE] ?? null;
        $this->envCost = $_ENV[self::COST_VARIABLE] ?? null;
        unset($_SERVER[self::COST_VARIABLE], $_ENV[self::COST_VARIABLE]);
    }

    protected function tearDown(): void
    {
        unset($_SERVER[self::COST_VARIABLE], $_ENV[self::COST_VARIABLE]);

        if (null !== $this->serverCost) {
            $_SERVER[self::COST_VARIABLE] = $this->serverCost;
        }

        if (null !== $this->envCost) {
            $_ENV[self::COST_VARIABLE] = $this->envCost;
        }
    }

    public function testHashUsesPhpDefaultCostWhenNoneIsConfigured(): void
    {
        $defaultCost = password_get_info(password_hash('reference', \PASSWORD_BCRYPT))['options']['cost'];

        $hash = Password::hash('secret');

        self::assertSame('2y', password_get_info($hash)['algo']);
        self::assertSame($defaultCost, password_get_info($hash)['options']['cost']);
        self::assertTrue(password_verify('secret', $hash));
    }

    public function testHashUsesTheConfiguredCost(): void
    {
        $_SERVER[self::COST_VARIABLE] = '5';

        $hash = Password::hash('secret');

        self::assertSame(5, password_get_info($hash)['options']['cost']);
        self::assertTrue(password_verify('secret', $hash));
    }

    public function testHashReadsTheCostFromEnvWhenServerHasNone(): void
    {
        $_ENV[self::COST_VARIABLE] = '6';

        self::assertSame(6, password_get_info(Password::hash('secret'))['options']['cost']);
    }

    public function testHashRejectsACostThatIsNotAnInteger(): void
    {
        $_SERVER[self::COST_VARIABLE] = 'low';

        $this->expectException(\InvalidArgumentException::class);

        Password::hash('secret');
    }

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
