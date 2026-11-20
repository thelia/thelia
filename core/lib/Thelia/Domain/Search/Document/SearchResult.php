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

#[Exclude]
final readonly class SearchResult
{
    public int $unfilteredTotal;

    /**
     * @param list<SearchHit> $hits
     */
    public function __construct(
        public array $hits,
        public int $total,
        ?int $unfilteredTotal = null,
    ) {
        $this->unfilteredTotal = $unfilteredTotal ?? $total;
    }

    /**
     * @return list<int>
     */
    public function idsOf(string $family): array
    {
        $ids = [];

        foreach ($this->hits as $hit) {
            if ($hit->family === $family) {
                $ids[] = $hit->id;
            }
        }

        return $ids;
    }
}
