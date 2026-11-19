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

namespace Thelia\Domain\Search;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Thelia\Domain\Search\Contract\SearchRedirectResolverInterface;
use Thelia\Domain\Search\Contract\SearchTermExpanderInterface;
use Thelia\Domain\Search\Contract\SearchTermRewriterInterface;
use Thelia\Domain\Search\Dictionary\SearchRedirectTargets;
use Thelia\Domain\Search\Document\SearchHit;
use Thelia\Domain\Search\Document\SearchQuery;
use Thelia\Domain\Search\Document\SearchResult;

final readonly class SearchService
{
    /**
     * @param iterable<SearchTermRewriterInterface>     $rewriters
     * @param iterable<SearchRedirectResolverInterface> $redirectResolvers
     * @param iterable<SearchTermExpanderInterface>     $expanders
     */
    public function __construct(
        private SearchEngineRegistry $engines,
        private SearchDocumentProviders $providers,
        #[AutowireIterator(SearchTermRewriterInterface::TAG)]
        private iterable $rewriters,
        #[AutowireIterator(SearchRedirectResolverInterface::TAG)]
        private iterable $redirectResolvers,
        private LoggerInterface $logger,
        #[AutowireIterator(SearchTermExpanderInterface::TAG)]
        private iterable $expanders = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function expand(string $term, ?string $locale): array
    {
        $alternatives = [];

        foreach ($this->expanders as $expander) {
            try {
                $alternatives = [...$alternatives, ...$expander->expand($term, $locale)];
            } catch (\Throwable $exception) {
                $this->logger->error('Search term expander {expander} failed ({exception}), its terms are left out.', [
                    'expander' => $expander::class,
                    'exception' => $exception::class,
                ]);
            }
        }

        $alternatives = array_values(array_filter(
            array_unique(array_map(trim(...), $alternatives)),
            static fn (string $alternative): bool => '' !== $alternative && $alternative !== trim($term),
        ));

        return \array_slice($alternatives, 0, SearchQuery::MAX_ALTERNATIVE_TERMS);
    }

    public function rewrite(string $term, ?string $locale): string
    {
        foreach ($this->rewriters as $rewriter) {
            try {
                $term = $rewriter->rewrite($term, $locale);
            } catch (\Throwable $exception) {
                $this->logger->error('Search term rewriter {rewriter} failed ({exception}), the term is kept as it was.', [
                    'rewriter' => $rewriter::class,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $term;
    }

    public function redirectFor(string $term, ?string $locale): ?string
    {
        foreach ($this->redirectResolvers as $resolver) {
            try {
                $path = $resolver->redirectFor($term, $locale);
            } catch (\Throwable $exception) {
                $this->logger->error('Search redirect resolver {resolver} failed ({exception}), the next one is asked.', [
                    'resolver' => $resolver::class,
                    'exception' => $exception::class,
                ]);

                continue;
            }

            if (null === $path) {
                continue;
            }

            if (!SearchRedirectTargets::isInternalPath($path)) {
                $this->logger->warning('Search redirect resolver {resolver} gave a path outside the shop, ignored.', ['resolver' => $resolver::class]);

                continue;
            }

            return $path;
        }

        return null;
    }

    public function hasEngine(): bool
    {
        return $this->engines->hasEngine();
    }

    public function search(SearchQuery $query): ?SearchResult
    {
        $engine = null;

        try {
            $engine = $this->engines->engine();

            if (null === $engine) {
                return null;
            }

            $query = $query->withTerm($this->rewrite($query->term, $query->locale));

            if ('' === trim($query->term)) {
                return new SearchResult([], 0);
            }

            if (!$engine->isAvailable()) {
                $this->logger->warning('Search engine {engine} unavailable, the database answers instead.', ['engine' => $engine::class]);

                return null;
            }

            $query = $query->withAlternativeTerms(\array_slice(array_values(array_unique([...$query->alternativeTerms, ...$this->expand($query->term, $query->locale)])), 0, SearchQuery::MAX_ALTERNATIVE_TERMS));
            $result = $engine->search($query);

            return $query->visibleOnly ? $this->withoutHiddenHits($result) : $result;
        } catch (\Throwable $exception) {
            $this->logger->error('Search engine {engine} failed ({exception}), the database answers instead.', [
                'engine' => null === $engine ? SearchEngineRegistry::class : $engine::class,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    private function withoutHiddenHits(SearchResult $result): SearchResult
    {
        $idsByFamily = [];

        foreach ($result->hits as $hit) {
            $idsByFamily[$hit->family][] = $hit->id;
        }

        $visible = [];

        foreach ($idsByFamily as $family => $ids) {
            $visible[$family] = array_flip($this->providers->get($family)?->visibleIds($ids) ?? []);
        }

        $hits = array_values(array_filter($result->hits, static fn (SearchHit $hit): bool => isset($visible[$hit->family][$hit->id])));
        $hidden = \count($result->hits) - \count($hits);

        return 0 === $hidden ? $result : new SearchResult($hits, max(\count($hits), $result->total - $hidden));
    }
}
