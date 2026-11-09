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

namespace Thelia\Domain\Search\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Thelia\Domain\Search\Document\SearchDocument;

#[AutoconfigureTag(self::TAG)]
interface SearchableDocumentProviderInterface
{
    public const string TAG = 'thelia.search.document_provider';

    public function family(): string;

    public function scope(): SearchScope;

    public function isTranslatable(): bool;

    /**
     * @return array<string, int>
     */
    public function fieldWeights(): array;

    /**
     * @return list<int>
     */
    public function idsAfter(int $afterId, int $limit): array;

    /**
     * @param list<int> $ids
     *
     * @return list<SearchDocument>
     */
    public function documents(array $ids, ?string $locale): array;

    /**
     * @param list<int> $ids
     *
     * @return list<int>
     */
    public function visibleIds(array $ids): array;
}
