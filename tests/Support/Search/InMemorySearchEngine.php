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

namespace Thelia\Tests\Support\Search;

use Thelia\Domain\Search\Contract\SearchEngineInterface;
use Thelia\Domain\Search\Document\SearchDocument;
use Thelia\Domain\Search\Document\SearchHit;
use Thelia\Domain\Search\Document\SearchQuery;
use Thelia\Domain\Search\Document\SearchResult;

final class InMemorySearchEngine implements SearchEngineInterface
{
    /** @var array<string, SearchDocument> */
    public array $documents = [];

    /** @var list<string> */
    public array $purgedFamilies = [];

    public bool $available = true;

    public ?int $indexCallsBeforeFailure = null;

    public ?SearchQuery $lastQuery = null;

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function index(array $documents): void
    {
        if (null !== $this->indexCallsBeforeFailure && $this->indexCallsBeforeFailure-- <= 0) {
            throw new \RuntimeException('The engine went away.');
        }

        foreach ($documents as $document) {
            $this->documents[self::key($document->family, $document->id, $document->locale)] = $document;
        }
    }

    public function remove(string $family, array $ids, ?string $locale): void
    {
        foreach ($ids as $id) {
            unset($this->documents[self::key($family, $id, $locale)]);
        }
    }

    public function purge(string $family): void
    {
        $this->purgedFamilies[] = $family;

        foreach ($this->documents as $key => $document) {
            if ($document->family === $family) {
                unset($this->documents[$key]);
            }
        }
    }

    public function search(SearchQuery $query): SearchResult
    {
        $this->lastQuery = $query;
        $term = mb_strtolower($query->term);
        $hits = [];

        foreach ($this->documents as $document) {
            if (!$query->accepts($document)) {
                continue;
            }

            $score = 0;

            foreach ($document->fields as $field => $text) {
                if (str_contains(mb_strtolower($text), $term)) {
                    $score += $document->weightOf($field);
                }
            }

            if ($score > 0) {
                $hits[] = new SearchHit($document->family, $document->id, (float) $score);
            }
        }

        usort($hits, static fn (SearchHit $left, SearchHit $right): int => $right->score <=> $left->score);

        return new SearchResult(\array_slice($hits, $query->offset, $query->limit), \count($hits));
    }

    public function has(string $family, int $id, ?string $locale): bool
    {
        return isset($this->documents[self::key($family, $id, $locale)]);
    }

    public function get(string $family, int $id, ?string $locale): ?SearchDocument
    {
        return $this->documents[self::key($family, $id, $locale)] ?? null;
    }

    private static function key(string $family, int $id, ?string $locale): string
    {
        return $family.'|'.$id.'|'.($locale ?? '-');
    }
}
