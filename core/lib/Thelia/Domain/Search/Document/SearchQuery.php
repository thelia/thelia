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

namespace Thelia\Domain\Search\Document;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Search\Contract\SearchScope;

#[Exclude]
final readonly class SearchQuery
{
    public const int MAX_LIMIT = 100;

    /**
     * @param list<string> $families
     */
    public function __construct(
        public string $term,
        public ?string $locale,
        public array $families = [],
        public SearchScope $scope = SearchScope::Shop,
        public bool $visibleOnly = true,
        public int $limit = 20,
        public int $offset = 0,
    ) {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException(\sprintf('A search returns from 1 to %d hits, %d asked for.', self::MAX_LIMIT, $limit));
        }

        if ($offset < 0) {
            throw new \InvalidArgumentException(\sprintf('A search starts at offset 0 or after, %d asked for.', $offset));
        }
    }

    public function withTerm(string $term): self
    {
        return new self($term, $this->locale, $this->families, $this->scope, $this->visibleOnly, $this->limit, $this->offset);
    }

    public function accepts(SearchDocument $document): bool
    {
        return $document->scope === $this->scope
            && ($document->visible || !$this->visibleOnly)
            && ([] === $this->families || \in_array($document->family, $this->families, true))
            && (null === $document->locale || $document->locale === $this->locale);
    }
}
