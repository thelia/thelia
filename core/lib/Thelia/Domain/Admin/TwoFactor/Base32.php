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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class Base32
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function encode(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(\ord($byte)), 8, '0', \STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return '' === $bytes ? '' : $encoded;
    }

    public static function decode(string $encoded): string
    {
        $normalized = strtoupper(str_replace([' ', '='], '', $encoded));
        $bits = '';

        foreach (str_split($normalized) as $character) {
            $position = strpos(self::ALPHABET, $character);

            if (false === $position) {
                throw new \InvalidArgumentException('The value is not base32 encoded.');
            }

            $bits .= str_pad(decbin($position), 5, '0', \STR_PAD_LEFT);
        }

        $bytes = '';

        foreach (str_split($bits, 8) as $byte) {
            if (8 === \strlen($byte)) {
                $bytes .= \chr((int) bindec($byte));
            }
        }

        return '' === $normalized ? '' : $bytes;
    }
}
