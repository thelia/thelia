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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Model\ConfigQuery;

final readonly class SharedCachePolicy
{
    public const SHAREABLE_VIEWS = ['index', 'category', 'product', 'content', 'folder', 'brand', 'sale'];

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

    public function __construct(private PricingActivityChecker $pricingActivityChecker)
    {
    }

    public function isShareable(Request $request, Response $response): bool
    {
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return false;
        }

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            return false;
        }

        if ($request->cookies->count() > 0 || $request->headers->has('Authorization') || $request->isXmlHttpRequest()) {
            return false;
        }

        if ($response->headers->hasCacheControlDirective('no-store')) {
            return false;
        }

        if (!\in_array((string) $request->attributes->get('_view', ''), self::SHAREABLE_VIEWS, true)) {
            return false;
        }

        if ($this->isSensitivePath($request->getPathInfo())) {
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

        foreach (self::SENSITIVE_PREFIXES as $prefix) {
            if ($segment === $prefix || str_starts_with($segment, $prefix.'-') || str_starts_with($segment, $prefix.'_')) {
                return true;
            }
        }

        return false;
    }
}
