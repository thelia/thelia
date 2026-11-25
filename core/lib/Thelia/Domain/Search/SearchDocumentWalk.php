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

use Thelia\Domain\Search\Contract\SearchableDocumentProviderInterface;

final class SearchDocumentWalk
{
    /**
     * @return list<int>
     *
     * @throws UnorderedDocumentIdentifiersException
     */
    public static function idsAfter(SearchableDocumentProviderInterface $provider, int $afterId, int $limit): array
    {
        $ids = $provider->idsAfter($afterId, $limit);
        $ascending = $ids;
        sort($ascending);

        if ($ascending !== $ids || ([] !== $ids && $ids[0] <= $afterId)) {
            throw new UnorderedDocumentIdentifiersException(\sprintf('The search document provider %s returns identifiers after #%d that are not in ascending order or not after it, the walk cannot tell where it stopped.', $provider::class, $afterId));
        }

        return $ids;
    }

    /**
     * @return \Generator<int, list<int>>
     *
     * @throws UnorderedDocumentIdentifiersException
     */
    public static function batches(SearchableDocumentProviderInterface $provider, int $afterId, int $limit): \Generator
    {
        while ([] !== ($ids = self::idsAfter($provider, $afterId, $limit))) {
            yield $ids;
            $afterId = max($ids);
        }
    }
}
