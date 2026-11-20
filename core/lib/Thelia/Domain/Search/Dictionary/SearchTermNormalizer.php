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

namespace Thelia\Domain\Search\Dictionary;

final class SearchTermNormalizer
{
    public static function normalize(string $term): string
    {
        $composed = \Normalizer::normalize($term, \Normalizer::FORM_C);
        $term = false === $composed ? $term : $composed;
        $singleSpaced = preg_replace('/[\s\x{200B}\x{FEFF}]+/u', ' ', $term);

        return mb_strtolower(trim(null === $singleSpaced ? $term : $singleSpaced));
    }

    public static function key(string $term): string
    {
        $term = self::normalize($term);
        $decomposed = \Normalizer::normalize($term, \Normalizer::FORM_D);

        if (!\is_string($decomposed)) {
            return $term;
        }

        $withoutMarks = preg_replace('/\p{Mn}+/u', '', $decomposed);

        return null === $withoutMarks ? $term : $withoutMarks;
    }

    /**
     * @param iterable<string> $terms
     *
     * @return list<string>
     */
    public static function normalizeAll(iterable $terms): array
    {
        $normalized = [];

        foreach ($terms as $term) {
            $term = self::normalize($term);

            if ('' !== $term) {
                $normalized[$term] = $term;
            }
        }

        return array_values($normalized);
    }
}
