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

namespace Thelia\Domain\Ai\Tool\Internal;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;

/**
 * The input schema of a tool as the core sends it to a provider or an MCP client.
 *
 * @internal
 */
#[Exclude]
final class AiToolSchema
{
    private function __construct()
    {
    }

    /**
     * Checks the rule of the schemas (an object that refuses unknown properties) and turns every empty
     * `properties` into an object: PHP encodes an empty array as `[]`, which providers reject.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public static function forTransport(string $toolName, array $schema): array
    {
        if ('object' !== ($schema['type'] ?? null) || false !== ($schema['additionalProperties'] ?? null)) {
            throw new InvalidAiArgumentException(\sprintf('The input schema of the tool "%s" must be {"type": "object", "additionalProperties": false}.', $toolName));
        }

        return self::normalize($schema);
    }

    /**
     * @param array<mixed> $node
     *
     * @return array<mixed>
     */
    private static function normalize(array $node): array
    {
        foreach ($node as $key => $value) {
            if ('properties' === $key && [] === $value) {
                $node[$key] = new \stdClass();
            } elseif (\is_array($value)) {
                $node[$key] = self::normalize($value);
            }
        }

        return $node;
    }
}
