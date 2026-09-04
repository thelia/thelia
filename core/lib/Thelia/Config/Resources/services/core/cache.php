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

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Thelia\Core\Cache\ApplicationCacheAdapter;
use Thelia\Core\Cache\CacheAdapterFactory;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Backend of every application cache pool, cache.app included. Which one it
    // is depends on THELIA_CACHE_DSN and on nothing else, so a shop moves from
    // the local disk to a shared cache server without a line of code changing.
    // The first two arguments belong to Symfony: it replaces them per pool, and
    // it reads the class below to decide whether a pool can be pruned, so every
    // backend answers under the same one.
    $services->set('thelia.cache.adapter', ApplicationCacheAdapter::class)
        ->abstract()
        ->factory([CacheAdapterFactory::class, 'create'])
        ->args([
            '', // namespace
            0, // default lifetime
            '%env(THELIA_CACHE_DSN)%',
            '%thelia.cache.directory%',
            service('cache.default_marshaller')->ignoreOnInvalid(),
        ])
        ->call('setLogger', [service('logger')->ignoreOnInvalid()])
        ->tag('cache.pool', ['clearer' => 'cache.default_clearer', 'reset' => 'reset'])
        ->tag('monolog.logger', ['channel' => 'cache']);

    // Configuration and feed cache, deliberately tied to the container cache
    // directory: it is meant to go away with cache:clear.
    $services->set(AdapterInterface::class, FilesystemAdapter::class)
        ->public()
        ->args([
            '%thelia.cache.namespace%',
            '600',
            '%kernel.cache_dir%',
        ]);

    $services->alias('thelia.cache', AdapterInterface::class)
        ->public();
};
