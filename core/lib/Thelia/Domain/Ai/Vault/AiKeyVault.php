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
 * Only a variable named like an API key ("…_KEY", "…_TOKEN") is ever read, and never one of the application
 * itself: the name is chosen in the settings, and APP_SECRET, DATABASE_PASSWORD or JWT_PASSPHRASE would
 * otherwise leave the server as the key of a provider.
 *
 * @internal
 */
class AiKeyVault
{
    private const string NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';
    private const string KEY_NAME_PATTERN = '/_(KEY|TOKEN)$/i';
    private const string APPLICATION_PREFIX_PATTERN = '/^(APP|DATABASE|JWT)_/i';

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

    /**
     * @throws InvalidAiArgumentException when the name is not one this vault reads
     */
    public function assertEnvironmentVariableName(string $name): void
    {
        if (1 !== preg_match(self::NAME_PATTERN, $name)) {
            throw new InvalidAiArgumentException('The name of the environment variable that holds an API key must be letters, digits and underscores.');
        }

        if (1 !== preg_match(self::KEY_NAME_PATTERN, $name) || 1 === preg_match(self::APPLICATION_PREFIX_PATTERN, $name)) {
            throw new InvalidAiArgumentException('The environment variable that holds an API key must end with "_KEY" or "_TOKEN" and must not be one of the application (APP_, DATABASE_, JWT_).');
        }
    }

    private function readEnvironment(string $name): ?string
    {
        $this->assertEnvironmentVariableName($name);

        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
