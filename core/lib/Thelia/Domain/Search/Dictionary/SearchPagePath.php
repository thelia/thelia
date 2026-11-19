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

namespace Thelia\Domain\Search\Dictionary;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Thelia\Core\Routing\Rewriting\RewritingResolver;
use Thelia\Tools\URL;

final readonly class SearchPagePath
{
    public const string SEARCH_VIEW = 'search';
    private const string FRONT_CONTROLLER = '/index.php';

    public function __construct(
        #[Autowire(service: 'router.default')]
        private UrlMatcherInterface $matcher,
    ) {
    }

    public function leadsToTheSearch(string $path): bool
    {
        $query = parse_url($path, \PHP_URL_QUERY);
        parse_str(\is_string($query) ? $query : '', $parameters);

        if (self::isSearchView($parameters['view'] ?? null)) {
            return true;
        }

        $pathOnly = parse_url($path, \PHP_URL_PATH);

        if (!\is_string($pathOnly) || '' === $pathOnly) {
            return false;
        }

        $pathOnly = self::withoutFrontController(self::withoutShopBase(self::withoutDotSegments($pathOnly)));

        return $this->routesToTheSearch($pathOnly) || self::rewritesToTheSearch($pathOnly);
    }

    private function routesToTheSearch(string $path): bool
    {
        try {
            $attributes = $this->matcher->match($path);
        } catch (\Throwable) {
            return false;
        }

        return self::isSearchView($attributes['_view'] ?? null);
    }

    private static function rewritesToTheSearch(string $path): bool
    {
        if ('/' === $path) {
            return false;
        }

        try {
            return self::isSearchView((new RewritingResolver($path))->view);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function withoutDotSegments(string $path): string
    {
        $segments = [];

        foreach (explode('/', rawurldecode($path)) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return '/'.implode('/', $segments);
    }

    private static function withoutShopBase(string $path): string
    {
        $base = parse_url(URL::getInstance()->getBaseUrl(), \PHP_URL_PATH);
        $base = \is_string($base) ? rtrim($base, '/') : '';

        if (str_ends_with($base, self::FRONT_CONTROLLER)) {
            $base = substr($base, 0, -\strlen(self::FRONT_CONTROLLER));
        }

        if ('' === $base || ($path !== $base && !str_starts_with($path, $base.'/'))) {
            return $path;
        }

        return '/'.ltrim(substr($path, \strlen($base)), '/');
    }

    private static function withoutFrontController(string $path): string
    {
        if ($path === self::FRONT_CONTROLLER) {
            return '/';
        }

        return str_starts_with($path, self::FRONT_CONTROLLER.'/') ? substr($path, \strlen(self::FRONT_CONTROLLER)) : $path;
    }

    private static function isSearchView(mixed $view): bool
    {
        return \is_string($view) && self::SEARCH_VIEW === strtolower(trim($view));
    }
}
