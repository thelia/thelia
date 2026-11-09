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

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Thelia\Domain\Search\Contract\SearchableDocumentProviderInterface;

final readonly class SearchDocumentProviders
{
    /** @var array<string, SearchableDocumentProviderInterface> */
    private array $byFamily;

    /**
     * @param iterable<SearchableDocumentProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(SearchableDocumentProviderInterface::TAG)]
        iterable $providers,
    ) {
        $byFamily = [];

        foreach ($providers as $provider) {
            $byFamily[$provider->family()] ??= $provider;
        }

        $this->byFamily = $byFamily;
    }

    public function get(string $family): ?SearchableDocumentProviderInterface
    {
        return $this->byFamily[$family] ?? null;
    }

    /**
     * @return list<string>
     */
    public function families(): array
    {
        return array_keys($this->byFamily);
    }
}
