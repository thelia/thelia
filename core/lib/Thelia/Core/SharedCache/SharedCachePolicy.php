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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Domain\Localization\VisitorCountry\VisitorCountryMode;
use Thelia\Domain\Localization\VisitorCountry\VisitorCountrySource;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Model\ConfigQuery;

final readonly class SharedCachePolicy
{
    public const SHAREABLE_VIEWS = ['index', 'category', 'product', 'content', 'folder', 'brand'];

    /**
     * The routes shared like the views, with the keys their answer is tagged with. The
     * sitemap of the theme reads the catalogue without going through `resources()`, so it
     * carries the list keys a change of a product, a category, a content, a folder or a
     * brand purges.
     */
    public const SHAREABLE_ROUTES = [
        'front_sitemap_index' => self::SITEMAP_KEYS,
        'front_sitemap_section' => self::SITEMAP_KEYS,
    ];

    private const SITEMAP_KEYS = ['products', 'product_images', 'categories', 'contents', 'folders', 'brands'];

    public const SENSITIVE_PREFIXES = [
        'admin',
        'api',
        'open_api',
        '_components',
        '_profiler',
        '_wdt',
        'account',
        'checkout',
        'customer',
        'order',
        'password',
        'login',
        'logout',
        'register',
        'address',
        'cart-reminder',
        'back-to-cart',
    ];

    public const TTL_CONFIG_KEY = 'shared_cache_ttl';

    public const DEFAULT_TTL = 300;

    public const MAX_TTL = 86400;

    /** @var list<string> */
    private array $shareableViews;

    /**
     * @param string[]                                  $neverSharedPrefixes the first segments of the addresses that are never
     *                                                                       shared, the parameter `thelia.shared_cache.never_shared_prefixes`,
     *                                                                       read without their slashes and case
     * @param iterable<SharedCacheContributorInterface> $contributors        the modules that add views of their own to {@see self::SHAREABLE_VIEWS}
     */
    public function __construct(
        private PricingActivityChecker $pricingActivityChecker,
        private SharedCacheKeyCollector $keyCollector,
        #[Autowire(param: 'thelia.shared_cache.never_shared_prefixes')]
        private array $neverSharedPrefixes = self::SENSITIVE_PREFIXES,
        #[AutowireIterator(SharedCacheContributorInterface::TAG)]
        iterable $contributors = [],
    ) {
        $views = self::SHAREABLE_VIEWS;

        foreach ($contributors as $contributor) {
            $views = [...$views, ...$contributor->shareableViews()];
        }

        $this->shareableViews = array_values(array_unique($views));
    }

    public function isTaggable(Request $request, Response $response): bool
    {
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return false;
        }

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            return false;
        }

        if (!\in_array((string) $request->attributes->get('_view', ''), $this->shareableViews, true)
            && !isset(self::SHAREABLE_ROUTES[(string) $request->attributes->get('_route', '')])) {
            return false;
        }

        return !$this->isSensitivePath($request->getPathInfo());
    }

    /**
     * @return list<string>
     */
    public function routeKeys(Request $request): array
    {
        return self::SHAREABLE_ROUTES[(string) $request->attributes->get('_route', '')] ?? [];
    }

    public function isShareable(Request $request, Response $response): bool
    {
        if (!$this->isTaggable($request, $response)) {
            return false;
        }

        if ($request->cookies->count() > 0 || $request->headers->has('Authorization') || $request->isXmlHttpRequest()) {
            return false;
        }

        if ($response->headers->hasCacheControlDirective('no-store') || !$this->keyCollector->isComplete()) {
            return false;
        }

        // A page priced or offered in the country of the visitor cannot be told apart by an
        // address: only a header can be a cache key, so a country read from the IP is personal.
        if (VisitorCountryMode::Off !== VisitorCountryMode::fromShopConfiguration()
            && VisitorCountrySource::IpDatabase === VisitorCountrySource::fromShopConfiguration()) {
            return false;
        }

        return !$this->pricingActivityChecker->hasVisitorDependentPricing();
    }

    public static function ttl(): int
    {
        $ttl = (int) ConfigQuery::read(self::TTL_CONFIG_KEY, (string) self::DEFAULT_TTL);

        return $ttl < 1 || $ttl > self::MAX_TTL ? self::DEFAULT_TTL : $ttl;
    }

    private function isSensitivePath(string $path): bool
    {
        $segment = strtolower(explode('/', ltrim($path, '/'), 2)[0]);

        foreach ($this->neverSharedPrefixes as $prefix) {
            $prefix = strtolower(trim($prefix, '/'));

            if ('' === $prefix) {
                continue;
            }

            if ($segment === $prefix || str_starts_with($segment, $prefix.'-') || str_starts_with($segment, $prefix.'_')) {
                return true;
            }
        }

        return false;
    }
}
