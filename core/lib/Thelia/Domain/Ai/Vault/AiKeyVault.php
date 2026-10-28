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

use Thelia\Domain\Ai\Exception\AiKeyDecryptionException;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Model\AiConnection;

/**
 * Gives the API key of a connection to the code that calls the provider.
 *
 * The environment variable the connection names (`api_key_env`) wins over the key stored in the database:
 * a project that keeps its secrets out of the database sets it and never stores anything. A stored key that
 * this application secret cannot open is an error, never a key to send as it is.
 *
 * @internal
 */
class AiKeyVault
{
    public function __construct(private readonly AiKeyCipher $cipher)
    {
    }

    /**
     * @return string|null null when the connection has no key (a self-hosted server without authentication)
     *
     * @throws AiKeyDecryptionException
     */
    public function resolve(AiConnection $connection): ?string
    {
        return $this->resolveFor($connection->getCode(), $connection->getApiKeyEncrypted(), $connection->getApiKeyEnv());
    }

    /**
     * @throws AiKeyDecryptionException
     */
    public function resolveFor(string $connectionCode, ?string $encryptedKey, ?string $environmentVariable): ?string
    {
        if (null !== $environmentVariable && '' !== $environmentVariable) {
            $fromEnvironment = $this->readEnvironment($environmentVariable);

            if (null !== $fromEnvironment) {
                return $fromEnvironment;
            }
        }

        if (null === $encryptedKey || '' === $encryptedKey) {
            return null;
        }

        return $this->cipher->decrypt($encryptedKey) ?? throw new AiKeyDecryptionException($connectionCode);
    }

    public function encrypt(#[\SensitiveParameter] string $plainKey): string
    {
        return $this->cipher->encrypt($plainKey);
    }

    private function readEnvironment(string $name): ?string
    {
        if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidAiArgumentException('The name of the environment variable that holds an API key must be letters, digits and underscores.');
        }

        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
