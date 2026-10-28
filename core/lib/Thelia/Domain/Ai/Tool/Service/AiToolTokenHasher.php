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

namespace Thelia\Domain\Ai\Tool\Service;

/**
 * The format of an MCP token, its fingerprint and the comparison of fingerprints.
 *
 * A token is `thelia_mcp_` and 43 characters of base62 drawn from random_bytes (about 256 bits): the secret is
 * too long to guess, so a plain SHA-256 is stored, not a slow hash. Fingerprints are compared in constant time.
 *
 * @internal
 */
final readonly class AiToolTokenHasher
{
    public const string PREFIX = 'thelia_mcp_';

    public const int RANDOM_LENGTH = 43;

    /** The first characters of a token kept to recognise it: the prefix and four characters of the secret. */
    public const int DISPLAY_LENGTH = 15;

    private const string ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function generate(): string
    {
        $random = '';

        while (self::RANDOM_LENGTH > \strlen($random)) {
            foreach (str_split(random_bytes(64)) as $byte) {
                // 248 = 4 * 62: the bytes above are dropped so that every character is equally likely.
                if (\ord($byte) < 248) {
                    $random .= self::ALPHABET[\ord($byte) % 62];
                }

                if (self::RANDOM_LENGTH === \strlen($random)) {
                    break;
                }
            }
        }

        return self::PREFIX.$random;
    }

    public function isWellFormed(#[\SensitiveParameter] string $token): bool
    {
        return 1 === preg_match('/^thelia_mcp_[0-9A-Za-z]{43}$/D', $token);
    }

    public function fingerprint(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }

    public function display(#[\SensitiveParameter] string $token): string
    {
        return substr($token, 0, self::DISPLAY_LENGTH);
    }

    public function matches(string $fingerprint, string $storedFingerprint): bool
    {
        return hash_equals($storedFingerprint, $fingerprint);
    }
}
