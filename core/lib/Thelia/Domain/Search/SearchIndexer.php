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

final readonly class SearchIndexer
{
    public function __construct(
        private SearchEngineRegistry $engines,
        private SearchDocumentProviders $providers,
        private SearchLocales $locales,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<int>         $ids
     * @param list<string>|null $locales
     */
    public function synchronize(string $family, array $ids, ?array $locales = null): void
    {
        $engine = $this->engines->engine();

        if (null === $engine || [] === $ids) {
            return;
        }

        $provider = $this->providers->get($family);

        if (null === $provider) {
            $this->logger->warning('Search indexing skipped: no document provider for the family {family}.', ['family' => $family]);

            return;
        }

        $startedAt = hrtime(true);
        $ids = array_values(array_unique($ids));

        foreach ($provider->isTranslatable() ? ($locales ?? $this->locales->indexed()) : [null] as $locale) {
            $documents = $provider->documents($ids, $locale);

            if ([] !== $documents) {
                $engine->index($documents);
            }

            $indexedIds = array_map(static fn ($document): int => $document->id, $documents);
            $missingIds = array_values(array_diff($ids, $indexedIds));

            if ([] !== $missingIds) {
                $engine->remove($family, $missingIds, $locale);
            }
        }

        $this->logger->info('Search indexing of {count} {family} document(s) took {milliseconds} ms.', [
            'count' => \count($ids),
            'family' => $family,
            'milliseconds' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
        ]);
    }
}
