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

namespace Thelia\Domain\Ai\Internal;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;

/**
 * The checks the value objects of the AI layer share, so that each rule is written once.
 *
 * @internal
 */
#[Exclude]
final class AiAssert
{
    /** `<module>.<purpose>`, lower case: `theliacms.page_generation`. */
    public const string USAGE_ID_PATTERN = '/^[a-z][a-z0-9_]{0,54}\.[a-z][a-z0-9_]{0,71}$/';

    /** A non-negative decimal written with a dot, as Propel reads a DECIMAL column: `0.05421000`. */
    private const string DECIMAL_PATTERN = '/^\d{1,8}(\.\d{1,8})?$/';

    /** A name a provider accepts for a tool or a schema. */
    private const string NAME_PATTERN = '/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/';

    /** A correlation identifier or a guardrail name: no space, no PII by construction. */
    private const string TOKEN_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/';

    private function __construct()
    {
    }

    public static function usageId(string $value): void
    {
        if (1 !== preg_match(self::USAGE_ID_PATTERN, $value)) {
            throw new InvalidAiArgumentException(\sprintf('"%s" is not a usage identifier: write it "<module>.<purpose>" in lower case, like "theliacms.page_generation".', $value));
        }
    }

    public static function decimal(string $value, string $field): void
    {
        if (1 !== preg_match(self::DECIMAL_PATTERN, $value)) {
            throw new InvalidAiArgumentException(\sprintf('%s must be a non-negative decimal string like "0.05421000", "%s" given.', $field, $value));
        }
    }

    public static function currency(string $value): void
    {
        if (1 !== preg_match('/^[A-Z]{3}$/', $value)) {
            throw new InvalidAiArgumentException(\sprintf('"%s" is not an ISO 4217 currency code.', $value));
        }
    }

    public static function name(string $value, string $field): void
    {
        if (1 !== preg_match(self::NAME_PATTERN, $value)) {
            throw new InvalidAiArgumentException(\sprintf('%s "%s" must start with a letter and hold at most 64 letters, digits, "_" or "-".', $field, $value));
        }
    }

    public static function token(string $value, string $field): void
    {
        if (1 !== preg_match(self::TOKEN_PATTERN, $value)) {
            throw new InvalidAiArgumentException(\sprintf('%s "%s" must hold at most 64 letters, digits, ".", "_", ":" or "-".', $field, $value));
        }
    }

    public static function notBlank(string $value, string $field): void
    {
        if ('' === trim($value)) {
            throw new InvalidAiArgumentException(\sprintf('%s must not be empty.', $field));
        }
    }

    public static function notNegative(int $value, string $field): void
    {
        if ($value < 0) {
            throw new InvalidAiArgumentException(\sprintf('%s must not be negative, %d given.', $field, $value));
        }
    }

    /**
     * @param array<mixed> $values
     * @param class-string $class
     */
    public static function listOf(array $values, string $class, string $field): void
    {
        if (!array_is_list($values)) {
            throw new InvalidAiArgumentException(\sprintf('%s must be a list.', $field));
        }

        foreach ($values as $value) {
            if (!$value instanceof $class) {
                throw new InvalidAiArgumentException(\sprintf('%s must hold %s objects only, %s given.', $field, $class, get_debug_type($value)));
            }
        }
    }

    /**
     * @param array<mixed> $values
     * @param class-string $class
     */
    public static function mapOf(array $values, string $class, string $field): void
    {
        foreach ($values as $key => $value) {
            if (!\is_string($key) || !$value instanceof $class) {
                throw new InvalidAiArgumentException(\sprintf('%s must map a name to a %s.', $field, $class));
            }
        }
    }

    /**
     * @param array<mixed> $options
     */
    public static function providerOptions(array $options): void
    {
        foreach ($options as $provider => $providerOptions) {
            if (!\is_string($provider) || null === AiProvider::tryFrom($provider) || !\is_array($providerOptions)) {
                throw new InvalidAiArgumentException(\sprintf('Provider options are keyed by an AiProvider value ("anthropic", "mistral"...) and hold an array, "%s" given.', $provider));
            }
        }
    }

    /**
     * @param array<mixed> $schema
     */
    public static function objectSchema(array $schema, string $field): void
    {
        if ('object' !== ($schema['type'] ?? null)) {
            throw new InvalidAiArgumentException(\sprintf('%s must be a JSON schema of "type": "object".', $field));
        }
    }
}
