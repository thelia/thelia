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

use Thelia\Domain\Ai\AiProvider;
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
 * Only a variable that {@see AiEnvironmentKey} accepts for the provider and the address of the connection is ever
 * read: the name and the address are chosen in the settings, and any secret of the server would otherwise leave it
 * as the key of a provider.
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
        return $this->resolveFor($connection->getCode(), $connection->getApiKeyEncrypted(), $connection->getApiKeyEnv(), AiProvider::tryFrom((string) $connection->getProvider()), $connection->getBaseUrl());
    }

    /**
     * @param ?AiProvider $provider the provider of the connection and its base URL, which decide what variable may be read
     *
     * @throws AiKeyDecryptionException
     * @throws InvalidAiArgumentException when the variable may not be read for this connection
     */
    public function resolveFor(string $connectionCode, ?string $encryptedKey, ?string $environmentVariable, ?AiProvider $provider = null, ?string $baseUrl = null): ?string
    {
        if (null !== $environmentVariable && '' !== $environmentVariable) {
            $fromEnvironment = AiEnvironmentKey::read($environmentVariable, $provider, $baseUrl);

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

    /**
     * @throws InvalidAiArgumentException when the variable may not be read for a connection of this provider at this base URL
     */
    public function assertEnvironmentVariableName(string $name, ?AiProvider $provider, ?string $baseUrl): void
    {
        AiEnvironmentKey::assertName($name, $provider, $baseUrl);
    }
}
