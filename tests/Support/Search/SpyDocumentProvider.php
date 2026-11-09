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

use Thelia\Domain\Search\Contract\SearchableDocumentProviderInterface;
use Thelia\Domain\Search\Contract\SearchScope;

final class SpyDocumentProvider implements SearchableDocumentProviderInterface
{
    /** @var list<string> */
    public array $calls = [];

    public bool $failing = false;

    /**
     * @param list<int>|null $visibleIds
     * @param list<int>      $ids
     */
    public function __construct(
        private readonly string $family = 'product',
        private readonly ?array $visibleIds = null,
        private readonly array $ids = [],
    ) {
    }

    public function family(): string
    {
        return $this->family;
    }

    public function scope(): SearchScope
    {
        return SearchScope::Shop;
    }

    public function isTranslatable(): bool
    {
        return true;
    }

    public function fieldWeights(): array
    {
        return [];
    }

    public function idsAfter(int $afterId, int $limit): array
    {
        $this->calls[] = 'idsAfter';

        return \array_slice(array_values(array_filter($this->ids, static fn (int $id): bool => $id > $afterId)), 0, $limit);
    }

    public function documents(array $ids, ?string $locale): array
    {
        $this->calls[] = 'documents';

        if ($this->failing) {
            throw new \RuntimeException('The engine went away.');
        }

        return [];
    }

    public function visibleIds(array $ids): array
    {
        $this->calls[] = 'visibleIds';

        return null === $this->visibleIds ? $ids : array_values(array_intersect($ids, $this->visibleIds));
    }
}
