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

namespace Thelia\Core\SharedCache;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\ResetInterface;

final class SharedCacheKeyCollector implements ResetInterface
{
    public const READABLE_PREFIXES = [
        '/api/front/products',
        '/api/front/product_sale_elements',
        '/api/front/product_images',
        '/api/front/product_sale_elements_product_image',
        '/api/front/product_association_types',
        '/api/front/product_associations',
        '/api/front/product_videos',
        '/api/front/product_sale_elements_product_video',
        '/api/front/tfilters',
        '/api/front/categories',
        '/api/front/contents',
        '/api/front/folders',
        '/api/front/brands',
        '/api/front/features',
        '/api/front/feature_values',
        '/api/front/attributes',
        '/api/front/attribute_values',
        '/api/front/languages',
        '/api/front/sales',
        '/api/front/countries',
        '/api/front/currencies',
        '/api/front/taxes',
    ];

    public const MAX_KEYS = 200;

    /** @var array<string, true> */
    private array $keys = [];

    private bool $readUnlistedResource = false;

    private bool $overflowed = false;

    /** @var array<string, list<string>> */
    private readonly array $contributedResources;

    /**
     * @param string[]                                  $readablePrefixes
     * @param iterable<SharedCacheContributorInterface> $contributors
     */
    public function __construct(
        #[Autowire(param: 'thelia.shared_cache.readable_prefixes')]
        private readonly array $readablePrefixes,
        #[AutowireIterator(SharedCacheContributorInterface::TAG)]
        iterable $contributors = [],
    ) {
        $contributedResources = [];

        foreach ($contributors as $contributor) {
            foreach ($contributor->readableResources() as $prefix => $keys) {
                $prefix = rtrim($prefix, '/');
                $contributedResources[$prefix] = array_values(array_unique([...$contributedResources[$prefix] ?? [], ...$keys]));
            }
        }

        $this->contributedResources = $contributedResources;
    }

    public function record(string $path): void
    {
        $path = (string) parse_url($path, \PHP_URL_PATH);

        if ($this->isReadable($path)) {
            $this->tag(...SharedCacheKey::forPath($path));

            return;
        }

        $contributedKeys = $this->contributedKeys($path);

        if (null === $contributedKeys) {
            $this->readUnlistedResource = true;

            return;
        }

        $this->tag(...$contributedKeys);
    }

    /**
     * Tags the page with keys of its own, for what it shows without reading it through
     * `resources()`: a module that renders a view of its own names what the view read.
     */
    public function tag(string ...$keys): void
    {
        foreach ($keys as $key) {
            if ('' === $key || isset($this->keys[$key])) {
                continue;
            }

            if (\count($this->keys) >= self::MAX_KEYS) {
                $this->overflowed = true;

                continue;
            }

            $this->keys[$key] = true;
        }
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->keys);
    }

    public function isComplete(): bool
    {
        return !$this->readUnlistedResource && !$this->overflowed;
    }

    public function reset(): void
    {
        $this->keys = [];
        $this->readUnlistedResource = false;
        $this->overflowed = false;
    }

    private function isReadable(string $path): bool
    {
        foreach ($this->readablePrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>|null the keys a contributor gave the resource, null when no
     *                           contributor declared it
     */
    private function contributedKeys(string $path): ?array
    {
        foreach ($this->contributedResources as $prefix => $keys) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $keys;
            }
        }

        return null;
    }
}
