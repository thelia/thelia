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
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;

/**
 * The one place an API key is read from the environment, and the one rule on the name of the variable it is
 * read from. Only a variable named like an API key ("…_KEY", "…_TOKEN") is ever read, and never one of the
 * application itself: the name is chosen in the settings, and APP_SECRET, DATABASE_PASSWORD or JWT_PASSPHRASE
 * would otherwise leave the server as the key of a provider.
 *
 * A function of a name and the environment, with no service behind it, so that the vault that sends the key and
 * the model that tells the merchant it exists (`AiConnection::getHasKey()`, which Propel builds without the
 * container) cannot answer differently.
 *
 * @internal
 */
#[Exclude]
final class AiEnvironmentKey
{
    private const string NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';
    private const string KEY_NAME_PATTERN = '/_(KEY|TOKEN)$/i';
    private const string APPLICATION_PREFIX_PATTERN = '/^(APP|DATABASE|JWT)_/i';

    private function __construct()
    {
    }

    /**
     * @throws InvalidAiArgumentException when the name is not one this class reads
     */
    public static function assertName(string $name): void
    {
        if (1 !== preg_match(self::NAME_PATTERN, $name)) {
            throw new InvalidAiArgumentException('The name of the environment variable that holds an API key must be letters, digits and underscores.');
        }

        if (1 !== preg_match(self::KEY_NAME_PATTERN, $name) || 1 === preg_match(self::APPLICATION_PREFIX_PATTERN, $name)) {
            throw new InvalidAiArgumentException('The environment variable that holds an API key must end with "_KEY" or "_TOKEN" and must not be one of the application (APP_, DATABASE_, JWT_).');
        }
    }

    /**
     * @return string|null the trimmed value, null when the variable is unset or blank
     *
     * @throws InvalidAiArgumentException when the name is not one this class reads
     */
    public static function read(string $name): ?string
    {
        self::assertName($name);

        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
