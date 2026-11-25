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

use Thelia\Domain\Search\SearchDocumentProviders;
use Thelia\Model\Brand;
use Thelia\Model\BrandQuery;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Tools\RedirectUrl;
use Thelia\Tools\URL;

final readonly class SearchRedirectTargets
{
    public function __construct(
        private ?SearchDocumentProviders $providers = null,
    ) {
    }

    public function find(string $type, ?int $id): Product|Category|Content|Folder|Brand|null
    {
        if (null === $id || $id < 1) {
            return null;
        }

        return match ($type) {
            SearchRedirectTargetType::PRODUCT => ProductQuery::create()->findPk($id),
            SearchRedirectTargetType::CATEGORY => CategoryQuery::create()->findPk($id),
            SearchRedirectTargetType::CONTENT => ContentQuery::create()->findPk($id),
            SearchRedirectTargetType::FOLDER => FolderQuery::create()->findPk($id),
            SearchRedirectTargetType::BRAND => BrandQuery::create()->findPk($id),
            default => null,
        };
    }

    public function pathOf(string $type, ?int $id, ?string $url, ?string $locale): ?string
    {
        if (SearchRedirectTargetType::URL === $type) {
            return null !== $url && self::isInternalPath($url) ? self::pathOfAddress(URL::getInstance()->absoluteUrl($url)) : null;
        }

        $target = $this->find($type, $id);

        if (null === $target || !$this->isVisibleToTheVisitor($type, (int) $id, $target)) {
            return null;
        }

        return self::pathOfAddress((string) $target->getUrl($locale));
    }

    private function isVisibleToTheVisitor(string $type, int $id, Product|Category|Content|Folder|Brand $target): bool
    {
        $provider = $this->providers?->get($type);

        if (null === $provider) {
            return 1 === (int) $target->getVisible();
        }

        return \in_array($id, $provider->visibleIds([$id]), true);
    }

    private static function pathOfAddress(string $address): ?string
    {
        $path = parse_url($address, \PHP_URL_PATH);

        if (!\is_string($path) || '' === $path) {
            return null;
        }

        $query = parse_url($address, \PHP_URL_QUERY);
        $path = '/'.ltrim($path, '/');

        return \is_string($query) && '' !== $query ? $path.'?'.$query : $path;
    }

    public static function isInternalPath(string $path): bool
    {
        return str_starts_with($path, '/')
            && 1 !== preg_match('/\s/u', $path)
            && RedirectUrl::isSafe($path, '');
    }
}
