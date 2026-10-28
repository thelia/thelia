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

/**
 * Follows a `$ref` that points inside the schema it is written in (`#`, `#/$defs/page`). Nothing else is
 * followed: a schema never makes the layer fetch another document.
 *
 * @internal
 */
#[Exclude]
final class AiSchemaReference
{
    private function __construct()
    {
    }

    /**
     * @param array<mixed> $root
     *
     * @return mixed null when the reference points outside the schema or at nothing
     */
    public static function resolve(array $root, string $reference): mixed
    {
        if ('#' === $reference) {
            return $root;
        }

        if (!str_starts_with($reference, '#/')) {
            return null;
        }

        $node = $root;

        foreach (explode('/', substr($reference, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], rawurldecode($segment));

            if (!\is_array($node) || !\array_key_exists($segment, $node)) {
                return null;
            }

            $node = $node[$segment];
        }

        return $node;
    }
}
