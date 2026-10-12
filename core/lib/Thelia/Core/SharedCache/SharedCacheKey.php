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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class SharedCacheKey
{
    private const SINGULARS = [
        'products' => 'product',
        'categories' => 'category',
        'brands' => 'brand',
        'contents' => 'content',
        'folders' => 'folder',
    ];

    public const ALL = 'thelia';

    private function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public static function forChange(string $family, int $id): array
    {
        return match ($family) {
            'product' => ['product-'.$id, 'products', 'product_sale_elements', 'product_images', 'product_sale_elements_product_image', 'product_videos', 'product_associations', 'tfilters'],
            'category' => ['category-'.$id, 'categories', 'products', 'tfilters'],
            'content' => ['content-'.$id, 'contents'],
            'folder' => ['folder-'.$id, 'folders', 'contents'],
            'brand' => ['brand-'.$id, 'brands', 'products', 'tfilters'],
            default => [self::ALL],
        };
    }

    public static function item(string $resource, int|string $id): string
    {
        return (self::SINGULARS[$resource] ?? $resource).'-'.$id;
    }

    /**
     * @return list<string>
     */
    public static function forPath(string $path): array
    {
        if (1 !== preg_match('#^/api/front/([a-z_]+)(?:/(\d+))?(?:/|$)#', $path, $matches)) {
            return [];
        }

        if (isset($matches[2]) && '' !== $matches[2]) {
            return [self::item($matches[1], $matches[2])];
        }

        return [$matches[1]];
    }
}
