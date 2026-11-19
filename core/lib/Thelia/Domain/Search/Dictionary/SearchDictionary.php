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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Model\LangQuery;
use Thelia\Model\SearchSynonymQuery;
use Thelia\Model\SearchTermRedirectQuery;

/**
 * @phpstan-type Redirect array{type: string, id: int|null, url: string|null}
 * @phpstan-type SynonymIndex array{groups: list<list<string>>, references: array<string, list<int>>}
 * @phpstan-type Entry array{synonyms: SynonymIndex, redirects: array<string, Redirect>}
 */
class SearchDictionary implements ResetInterface
{
    public const string CACHE_KEY = 'thelia_search_dictionary';

    /** @var array<string, Entry> */
    private array $entries = [];

    private bool $changedDuringTheRequest = false;

    public function __construct(
        #[Autowire(service: 'thelia.cache.config.adapter')]
        private readonly ?CacheItemPoolInterface $cache = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function synonymsOf(string $key, ?string $locale): array
    {
        return self::equivalentsOf($key, $this->entryOf($locale ?? '')['synonyms']);
    }

    /**
     * @return Redirect|null
     */
    public function redirectOf(string $key, ?string $locale): ?array
    {
        return $this->entryOf($locale ?? '')['redirects'][$key] ?? null;
    }

    public function forget(): void
    {
        $this->entries = [];

        if ($this->changedDuringTheRequest) {
            return;
        }

        $this->deleteCachedEntries();
        $this->changedDuringTheRequest = true;
    }

    public function forgetAfterTheRequest(): void
    {
        if (!$this->changedDuringTheRequest) {
            return;
        }

        $this->deleteCachedEntries();
        $this->changedDuringTheRequest = false;
    }

    public function reset(): void
    {
        $this->entries = [];
    }

    /**
     * @param iterable<array{term: string, synonyms: list<string>, bidirectional: bool}> $rows
     *
     * @return SynonymIndex
     */
    public static function synonymIndex(iterable $rows): array
    {
        $groups = [];
        $references = [];

        foreach ($rows as $row) {
            $term = SearchTermNormalizer::normalize($row['term']);
            $equivalents = SearchTermNormalizer::normalizeAll($row['synonyms']);
            $group = \count($groups);
            $groups[] = [$term, ...$equivalents];
            $references[SearchTermNormalizer::key($term)][] = $group * 2;

            if (!$row['bidirectional']) {
                continue;
            }

            foreach ($equivalents as $equivalent) {
                $references[SearchTermNormalizer::key($equivalent)][] = $group * 2 + 1;
            }
        }

        return ['groups' => $groups, 'references' => $references];
    }

    /**
     * @param SynonymIndex $index
     *
     * @return list<string>
     */
    public static function equivalentsOf(string $key, array $index): array
    {
        $equivalents = [];

        foreach (array_unique($index['references'][$key] ?? []) as $reference) {
            $group = $index['groups'][intdiv($reference, 2)] ?? [];

            foreach (0 === $reference % 2 ? \array_slice($group, 1) : $group as $equivalent) {
                if ('' !== $equivalent && SearchTermNormalizer::key($equivalent) !== $key) {
                    $equivalents[$equivalent] = $equivalent;
                }
            }
        }

        return array_values($equivalents);
    }

    /**
     * @return Entry
     */
    private function entryOf(string $locale): array
    {
        if (isset($this->entries[$locale])) {
            return $this->entries[$locale];
        }

        if (null === $this->cache || $this->changedDuringTheRequest) {
            return $this->entries[$locale] = self::load($locale);
        }

        $item = $this->cache->getItem(self::cacheKeyOf($locale));
        $cached = $item->isHit() ? $item->get() : null;

        if (\is_array($cached)) {
            return $this->entries[$locale] = $cached;
        }

        $this->entries[$locale] = self::load($locale);
        $this->cache->save($item->set($this->entries[$locale]));

        return $this->entries[$locale];
    }

    private function deleteCachedEntries(): void
    {
        if (null === $this->cache) {
            return;
        }

        $locales = array_map(strval(...), LangQuery::create()->select('Locale')->find()->getData());

        $this->cache->deleteItems(array_map(self::cacheKeyOf(...), ['', ...$locales]));
    }

    private static function cacheKeyOf(string $locale): string
    {
        return self::CACHE_KEY.'.'.hash('xxh128', $locale);
    }

    /**
     * @return Entry
     */
    private static function load(string $locale): array
    {
        $rows = [];

        $synonyms = SearchSynonymQuery::create()
            ->filterByActive(1)
            ->useLangQuery()
                ->filterByLocale($locale)
            ->endUse()
            ->orderById()
            ->find();

        foreach ($synonyms as $synonym) {
            $rows[] = [
                'term' => (string) $synonym->getTerm(),
                'synonyms' => explode("\n", (string) $synonym->getSynonyms()),
                'bidirectional' => 1 === $synonym->getBidirectional(),
            ];
        }

        $redirects = [];

        $activeRedirects = SearchTermRedirectQuery::create()
            ->filterByActive(1)
            ->useLangQuery()
                ->filterByLocale($locale)
            ->endUse()
            ->orderById()
            ->find();

        foreach ($activeRedirects as $redirect) {
            $redirects[SearchTermNormalizer::key((string) $redirect->getTerm())] ??= [
                'type' => (string) $redirect->getTargetType(),
                'id' => $redirect->getTargetId(),
                'url' => $redirect->getUrl(),
            ];
        }

        return ['synonyms' => self::synonymIndex($rows), 'redirects' => $redirects];
    }
}
