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
use Thelia\Domain\Search\Document\SearchQuery;
use Thelia\Domain\Search\Document\SearchResult;

#[AutoconfigureTag(self::TAG)]
interface SearchEngineInterface
{
    public const string TAG = 'thelia.search.engine';

    public function isAvailable(): bool;

    /**
     * @param list<SearchDocument> $documents
     */
    public function index(array $documents): void;

    /**
     * @param list<int> $ids
     */
    public function remove(string $family, array $ids, ?string $locale): void;

    public function purge(string $family): void;

    public function search(SearchQuery $query): SearchResult;
}
