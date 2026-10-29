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

namespace Thelia\Domain\Ai\Provider;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Whether a JSON schema may be sent with `strict: true`. OpenAI then refuses any schema in which an object, however
 * deep, is not closed (`additionalProperties: false`) or has a property that is not listed in `required`
 * (https://developers.openai.com/api/docs/guides/structured-outputs, "Supported schemas"). A schema that does not
 * qualify is sent with `strict: false`: the answer is still asked in this shape and checked by the layer afterwards.
 *
 * @internal
 */
#[Exclude]
final class AiStrictSchema
{
    /** Keywords whose value is a schema. */
    private const array SCHEMA_KEYWORDS = ['items', 'additionalItems', 'contains', 'not', 'if', 'then', 'else', 'propertyNames', 'unevaluatedItems', 'unevaluatedProperties'];

    /** Keywords whose value is a list of schemas. */
    private const array SCHEMA_LIST_KEYWORDS = ['anyOf', 'oneOf', 'allOf', 'prefixItems'];

    /** Keywords whose value is a map of schemas. */
    private const array SCHEMA_MAP_KEYWORDS = ['properties', 'patternProperties', '$defs', 'definitions', 'dependentSchemas'];

    private function __construct()
    {
    }

    /**
     * @param array<mixed> $schema
     */
    public static function accepts(array $schema): bool
    {
        if (self::isObject($schema)) {
            $properties = \is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
            $required = \is_array($schema['required'] ?? null) ? $schema['required'] : [];

            if (false !== ($schema['additionalProperties'] ?? null) || [] !== array_diff(array_map('strval', array_keys($properties)), array_map('strval', $required))) {
                return false;
            }
        }

        foreach (self::SCHEMA_KEYWORDS as $keyword) {
            if (\is_array($schema[$keyword] ?? null) && !self::acceptsAll([$schema[$keyword]])) {
                return false;
            }
        }

        foreach (self::SCHEMA_LIST_KEYWORDS as $keyword) {
            if (\is_array($schema[$keyword] ?? null) && !self::acceptsAll($schema[$keyword])) {
                return false;
            }
        }

        foreach (self::SCHEMA_MAP_KEYWORDS as $keyword) {
            if (\is_array($schema[$keyword] ?? null) && !self::acceptsAll($schema[$keyword])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<mixed> $schemas
     */
    private static function acceptsAll(array $schemas): bool
    {
        foreach ($schemas as $schema) {
            if (\is_array($schema) && !self::accepts($schema)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<mixed> $schema
     */
    private static function isObject(array $schema): bool
    {
        $type = $schema['type'] ?? null;

        return 'object' === $type || (\is_array($type) && \in_array('object', $type, true)) || \is_array($schema['properties'] ?? null);
    }
}
