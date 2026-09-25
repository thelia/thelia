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

namespace Thelia\Tests\Unit\Domain\Admin\TwoFactor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Admin\TwoFactor\Base32;
use Thelia\Domain\Admin\TwoFactor\Totp;

final class TotpTest extends TestCase
{
    private const RFC_SECRET = '12345678901234567890';

    public static function rfc6238Vectors(): iterable
    {
        yield [59, '287082'];
        yield [1111111109, '081804'];
        yield [1111111111, '050471'];
        yield [1234567890, '005924'];
        yield [2000000000, '279037'];
        yield [20000000000, '353130'];
    }

    #[DataProvider('rfc6238Vectors')]
    public function testTheCodeIsTheOneOfTheRfcTestVectors(int $timestamp, string $expectedCode): void
    {
        $totp = new Totp();

        self::assertSame($expectedCode, $totp->codeAt(Base32::encode(self::RFC_SECRET), $totp->stepAt($timestamp)));
    }

    public function testACodeOfTheCurrentStepIsAccepted(): void
    {
        $totp = new Totp();
        $secret = $totp->generateSecret();
        $now = 1_700_000_000;

        self::assertSame($totp->stepAt($now), $totp->matchingStep($secret, $totp->codeAt($secret, $totp->stepAt($now)), $now, null));
    }

    public function testACodeOfTheStepBeforeOrAfterIsAccepted(): void
    {
        $totp = new Totp();
        $secret = $totp->generateSecret();
        $now = 1_700_000_000;
        $step = $totp->stepAt($now);

        self::assertSame($step - 1, $totp->matchingStep($secret, $totp->codeAt($secret, $step - 1), $now, null));
        self::assertSame($step + 1, $totp->matchingStep($secret, $totp->codeAt($secret, $step + 1), $now, null));
    }

    public function testAClockThirtySecondsOffStillPassesAndFiveMinutesOffDoesNot(): void
    {
        $totp = new Totp();
        $secret = $totp->generateSecret();
        $phoneTime = 1_700_000_010;
        $code = $totp->codeAt($secret, $totp->stepAt($phoneTime));

        self::assertNotNull($totp->matchingStep($secret, $code, $phoneTime + 30, null));
        self::assertNull($totp->matchingStep($secret, $code, $phoneTime + 300, null));
    }

    public function testACodeOutsideTheToleranceIsRefused(): void
    {
        $totp = new Totp();
        $secret = $totp->generateSecret();
        $now = 1_700_000_000;
        $step = $totp->stepAt($now);

        self::assertNull($totp->matchingStep($secret, $totp->codeAt($secret, $step - 2), $now, null));
        self::assertNull($totp->matchingStep($secret, $totp->codeAt($secret, $step + 2), $now, null));
    }

    public function testACodeAlreadyUsedInItsStepIsRefused(): void
    {
        $totp = new Totp();
        $secret = $totp->generateSecret();
        $now = 1_700_000_000;
        $step = $totp->stepAt($now);
        $code = $totp->codeAt($secret, $step);

        self::assertNull($totp->matchingStep($secret, $code, $now, $step));
        self::assertNull($totp->matchingStep($secret, $totp->codeAt($secret, $step - 1), $now, $step));
    }

    public static function malformedCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['12345'];
        yield 'too long' => ['1234567'];
        yield 'letters' => ['12a456'];
        yield 'signed' => ['-12345'];
    }

    #[DataProvider('malformedCodes')]
    public function testAMalformedCodeIsRefused(string $code): void
    {
        $totp = new Totp();

        self::assertNull($totp->matchingStep($totp->generateSecret(), $code, 1_700_000_000, null));
    }

    public function testACodeTypedWithASpaceIsRead(): void
    {
        $totp = new Totp();
        $secret = $totp->generateSecret();
        $now = 1_700_000_000;
        $code = $totp->codeAt($secret, $totp->stepAt($now));

        self::assertNotNull($totp->matchingStep($secret, substr($code, 0, 3).' '.substr($code, 3), $now, null));
    }

    public function testTheSecretIsOneHundredAndSixtyRandomBits(): void
    {
        $totp = new Totp();
        $secret = $totp->generateSecret();

        self::assertSame(20, \strlen(Base32::decode($secret)));
        self::assertNotSame($secret, $totp->generateSecret());
    }

    public function testTheProvisioningUriCarriesWhatAnAuthenticatorAppReads(): void
    {
        $uri = (new Totp())->provisioningUri('JBSWY3DPEHPK3PXP', 'jane@example.com', 'My Shop');

        self::assertSame(
            'otpauth://totp/My%20Shop:jane%40example.com?secret=JBSWY3DPEHPK3PXP&issuer=My%20Shop&algorithm=SHA1&digits=6&period=30',
            $uri,
        );
    }

    public function testBase32RoundTripsAndMatchesTheRfc4648Vectors(): void
    {
        self::assertSame('MZXW6YTBOI', Base32::encode('foobar'));
        self::assertSame('MZXW6YQ', Base32::encode('foob'));
        self::assertSame('foobar', Base32::decode('MZXW6YTBOI======'));
        self::assertSame('foob', Base32::decode('mzxw 6yq'));
        self::assertSame('', Base32::encode(''));

        $bytes = random_bytes(20);
        self::assertSame($bytes, Base32::decode(Base32::encode($bytes)));
    }

    public function testBase32RefusesACharacterOutsideItsAlphabet(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Base32::decode('MZXW1');
    }
}
