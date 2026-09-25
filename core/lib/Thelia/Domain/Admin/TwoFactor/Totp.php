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

namespace Thelia\Domain\Admin\TwoFactor;

final readonly class Totp
{
    public const PERIOD = 30;
    public const DIGITS = 6;
    public const TOLERATED_STEPS = 1;
    private const SECRET_BYTES = 20;

    public function generateSecret(): string
    {
        return Base32::encode(random_bytes(self::SECRET_BYTES));
    }

    public function stepAt(int $timestamp): int
    {
        return intdiv($timestamp, self::PERIOD);
    }

    public function codeAt(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), Base32::decode($secret), true);
        $offset = \ord($hash[19]) & 0x0F;
        $binary = ((\ord($hash[$offset]) & 0x7F) << 24)
            | (\ord($hash[$offset + 1]) << 16)
            | (\ord($hash[$offset + 2]) << 8)
            | \ord($hash[$offset + 3]);

        return str_pad((string) ($binary % 10 ** self::DIGITS), self::DIGITS, '0', \STR_PAD_LEFT);
    }

    public function matchingStep(string $secret, string $code, int $timestamp, ?int $lastUsedStep): ?int
    {
        $code = str_replace(' ', '', $code);

        if (1 !== preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return null;
        }

        $currentStep = $this->stepAt($timestamp);
        $matchingStep = null;

        for ($step = $currentStep - self::TOLERATED_STEPS; $step <= $currentStep + self::TOLERATED_STEPS; ++$step) {
            if (hash_equals($this->codeAt($secret, $step), $code) && (null === $lastUsedStep || $step > $lastUsedStep)) {
                $matchingStep = $step;
            }
        }

        return $matchingStep;
    }

    public function provisioningUri(string $secret, string $accountName, string $issuer): string
    {
        return \sprintf(
            'otpauth://totp/%s:%s?%s',
            rawurlencode($issuer),
            rawurlencode($accountName),
            http_build_query([
                'secret' => $secret,
                'issuer' => $issuer,
                'algorithm' => 'SHA1',
                'digits' => self::DIGITS,
                'period' => self::PERIOD,
            ], '', '&', \PHP_QUERY_RFC3986),
        );
    }
}
