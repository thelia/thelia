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

use Thelia\Domain\Search\Contract\SearchTermExpanderInterface;
use Thelia\Domain\Search\Document\SearchQuery;

final readonly class DictionarySearchTermExpander implements SearchTermExpanderInterface
{
    public const int MAX_EXPANDED_WORDS = 8;

    public function __construct(private SearchDictionary $dictionary)
    {
    }

    public function expand(string $term, ?string $locale): array
    {
        $term = SearchTermNormalizer::normalize($term);

        if ('' === $term) {
            return [];
        }

        $alternatives = [];
        $termKey = SearchTermNormalizer::key($term);
        $add = static function (string $alternative) use (&$alternatives, $termKey): bool {
            if (SearchTermNormalizer::key($alternative) !== $termKey) {
                $alternatives[$alternative] = $alternative;
            }

            return \count($alternatives) >= SearchQuery::MAX_ALTERNATIVE_TERMS;
        };

        foreach ($this->dictionary->synonymsOf($termKey, $locale) as $synonym) {
            if ($add($synonym)) {
                return array_values($alternatives);
            }
        }

        $words = explode(' ', $term);

        if (\count($words) < 2 || \count($words) > self::MAX_EXPANDED_WORDS) {
            return array_values($alternatives);
        }

        foreach ($words as $position => $word) {
            foreach ($this->dictionary->synonymsOf(SearchTermNormalizer::key($word), $locale) as $synonym) {
                if ($add(implode(' ', array_replace($words, [$position => $synonym])))) {
                    return array_values($alternatives);
                }
            }
        }

        return array_values($alternatives);
    }
}
