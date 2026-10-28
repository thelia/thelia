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

namespace Thelia\Domain\Ai\Vault;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;

/**
 * Encrypts the API keys of the connections at rest: libsodium `secretbox` with a key derived (HKDF) from the
 * application secret and a context of its own, so the key of another feature built from the same secret
 * never opens these blobs.
 *
 * A stored blob reads `v1:` then base64(nonce . ciphertext). The class never logs and never puts a key
 * or a blob in an exception message.
 *
 * @internal
 */
class AiKeyCipher
{
    private const string CONTEXT = 'thelia.ai.api-key.v1';

    private const string PREFIX = 'v1:';

    private readonly string $key;

    public function __construct(
        #[\SensitiveParameter]
        #[Autowire('%kernel.secret%')]
        string $secret,
    ) {
        if ('' === trim($secret)) {
            throw new InvalidAiArgumentException('The application secret (APP_SECRET) is empty: API keys cannot be encrypted with it.');
        }

        $this->key = hash_hkdf('sha256', $secret, \SODIUM_CRYPTO_SECRETBOX_KEYBYTES, self::CONTEXT);
    }

    public function encrypt(#[\SensitiveParameter] string $plainKey): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX.base64_encode($nonce.sodium_crypto_secretbox($plainKey, $nonce, $this->key));
    }

    /**
     * @return string|null null when the blob is not one this secret produced
     */
    public function decrypt(#[\SensitiveParameter] string $blob): ?string
    {
        if (!str_starts_with($blob, self::PREFIX)) {
            return null;
        }

        $raw = base64_decode(substr($blob, \strlen(self::PREFIX)), true);

        if (false === $raw || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + \SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            return null;
        }

        $plain = sodium_crypto_secretbox_open(
            substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key,
        );

        return false === $plain ? null : $plain;
    }
}
