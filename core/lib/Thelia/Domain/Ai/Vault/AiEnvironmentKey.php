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

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Provider\AiProviderPresets;

/**
 * The one place an API key is read from the environment, and the one rule on the name of the variable it is
 * read from. The name is chosen in the settings, together with the address the key is sent to, so a variable
 * of the server (APP_SECRET, a payment key) would otherwise leave it as the key of a provider. Only two kinds
 * of variable are read:
 *
 * - one named THELIA_AI_…_KEY or THELIA_AI_…_TOKEN, set for the AI layer (THELIA_AI_PREVIOUS_SECRET, the former
 *   application secret, has neither ending), only while the connection calls the address of its provider or the
 *   base URL the server sets next to the key, THELIA_AI_…_BASE_URL: whoever sets the key on the server also says
 *   where it may go, as an administrator who changes the address of a stored key has to type it again;
 * - the usual variable of the provider (ANTHROPIC_API_KEY, MISTRAL_API_KEY, OPENAI_API_KEY), only while the
 *   connection calls the address of that provider.
 *
 * A function of a name, a provider, an address and the environment, with no service behind it, so that the
 * vault that sends the key and the model that tells the merchant it exists (`AiConnection::getHasKey()`, which
 * Propel builds without the container) cannot answer differently.
 *
 * @internal
 */
#[Exclude]
final class AiEnvironmentKey
{
    public const string INVALID_NAME = 'The name of the environment variable that holds an API key must be letters, digits and underscores.';

    public const string NOT_ALLOWED = 'The environment variable that holds an API key must start with "THELIA_AI_" and end with "_KEY" or "_TOKEN", or be the usual one of the provider (ANTHROPIC_API_KEY, MISTRAL_API_KEY, OPENAI_API_KEY).';

    public const string OTHER_ADDRESS = 'The usual environment variable of the provider is only sent to the address of the provider: for another base URL, name a variable that starts with "THELIA_AI_" and set its base URL next to it on the server (THELIA_AI_X_BASE_URL for THELIA_AI_X_KEY).';

    public const string ADDRESS_NOT_SET = 'The environment variable that holds an API key is only sent to the address of the provider, or to the base URL the server sets next to it: for THELIA_AI_X_KEY or THELIA_AI_X_TOKEN, set THELIA_AI_X_BASE_URL to the base URL of this connection on the server.';

    private const string NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    private const string LAYER_NAME_PATTERN = '/^THELIA_AI_([A-Z0-9_]*_)?(KEY|TOKEN)$/';

    private function __construct()
    {
    }

    /**
     * @param ?AiProvider $provider the provider of the connection, null when it is not one of the layer (then only a THELIA_AI_ variable is read)
     * @param ?string     $baseUrl  the base URL of the connection, null or empty for the address of the provider
     *
     * @throws InvalidAiArgumentException when the variable may not be read for this connection
     */
    public static function assertName(string $name, ?AiProvider $provider, ?string $baseUrl): void
    {
        if (1 !== preg_match(self::NAME_PATTERN, $name)) {
            throw new InvalidAiArgumentException(self::INVALID_NAME);
        }

        $presets = new AiProviderPresets();

        if (1 === preg_match(self::LAYER_NAME_PATTERN, $name)) {
            $defaultBaseUrl = null === $provider ? null : $presets->defaultBaseUrl($provider);

            if (!self::isTheAddressOfTheProvider($baseUrl, $defaultBaseUrl) && !self::isTheAddressSetNextTo($name, (string) $baseUrl)) {
                throw new InvalidAiArgumentException(self::ADDRESS_NOT_SET);
            }

            return;
        }

        if (null === $provider || $presets->apiKeyEnvironmentVariable($provider) !== $name) {
            throw new InvalidAiArgumentException(self::NOT_ALLOWED);
        }

        if (!self::isTheAddressOfTheProvider($baseUrl, $presets->defaultBaseUrl($provider))) {
            throw new InvalidAiArgumentException(self::OTHER_ADDRESS);
        }
    }

    /**
     * @return string|null the trimmed value, null when the variable is unset or blank
     *
     * @throws InvalidAiArgumentException when the variable may not be read for this connection
     */
    public static function read(string $name, ?AiProvider $provider, ?string $baseUrl): ?string
    {
        self::assertName($name, $provider, $baseUrl);

        return self::environment($name);
    }

    private static function environment(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    /**
     * THELIA_AI_X_BASE_URL for THELIA_AI_X_KEY and THELIA_AI_X_TOKEN, compared without its final slash.
     */
    private static function isTheAddressSetNextTo(string $name, string $baseUrl): bool
    {
        $variable = preg_replace('/_(KEY|TOKEN)$/D', '_BASE_URL', $name, 1, $replaced);
        $allowed = 1 === $replaced && \is_string($variable) ? self::environment($variable) : null;

        return null !== $allowed && '' !== trim($baseUrl) && 0 === strcasecmp(rtrim(trim($baseUrl), '/'), rtrim($allowed, '/'));
    }

    private static function isTheAddressOfTheProvider(?string $baseUrl, ?string $defaultBaseUrl): bool
    {
        $baseUrl = null === $baseUrl ? '' : rtrim(trim($baseUrl), '/');

        return '' === $baseUrl || (null !== $defaultBaseUrl && 0 === strcasecmp($baseUrl, rtrim($defaultBaseUrl, '/')));
    }
}
