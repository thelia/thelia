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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        // Cross-request cache for the data access layer. Off by default:
        // it trades catalog freshness (bounded by TTL, flushed on catalog
        // changes) for fewer pipeline replays. Enable per project in .env:
        //   THELIA_DATA_ACCESS_CACHE=1
        //   THELIA_DATA_ACCESS_CACHE_TTL=3600
        ->set('env(THELIA_DATA_ACCESS_CACHE)', '0')
        ->set('env(THELIA_DATA_ACCESS_CACHE_TTL)', '3600')
        ->set('thelia.api.data_access.cache.enabled', '%env(bool:THELIA_DATA_ACCESS_CACHE)%')
        ->set('thelia.api.data_access.cache.ttl', '%env(int:THELIA_DATA_ACCESS_CACHE_TTL)%')
        // Only user-independent, read-stable front resources are cacheable.
        // User/session scoped paths (account, cart, order…) are never cached.
        ->set('thelia.api.data_access.cache.allowed_prefixes', [
            '/api/front/products',
            '/api/front/product_sale_elements',
            '/api/front/product_images',
            '/api/front/product_sale_elements_product_image',
            '/api/front/categories',
            '/api/front/contents',
            '/api/front/folders',
            '/api/front/brands',
            '/api/front/features',
            '/api/front/feature_values',
            '/api/front/attributes',
            '/api/front/attribute_values',
            '/api/front/countries',
            '/api/front/currencies',
            '/api/front/taxes',
            '/api/front/modules',
        ]);
};
