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

use Symfony\Component\DependencyInjection\ContainerBuilder;

return static function (ContainerConfigurator $configurator, ContainerBuilder $container): void {
    // Symfony inlines the cache key seed when the container is compiled and
    // escapes what it inlines, so a default written as "%kernel.project_dir%"
    // would reach every installation as that literal text and put them all in
    // the same keyspace. The default is therefore built here, from values the
    // kernel has already resolved.
    $installation = $container->getParameter('kernel.project_dir').'/'.$container->getParameter('kernel.environment');

    $configurator->parameters()
        // Where the application cache pools are stored. Empty keeps them on the
        // local file system; a data source name moves them to a shared server:
        //   THELIA_CACHE_DSN=redis://localhost:6379
        ->set('env(THELIA_CACHE_DSN)', '')
        // Two installations sharing one cache server must not share a key, and
        // neither must the development and the production of one shop. The seed
        // defaults to the install path plus the environment; set the variable to
        // something the shop owns when several machines serve the same shop from
        // different paths, so that they do share their cache. It is read when the
        // container is compiled, so a change takes a cache clear.
        ->set('env(THELIA_CACHE_PREFIX_SEED)', $installation)
        // Outside var/cache/<env> on purpose: emptying the shop cache, from the
        // back office or with cache:clear, must not take the API refresh tokens
        // with it. Pools are emptied one by one with cache:pool:clear.
        ->set('thelia.cache.directory', '%kernel.project_dir%/var/pools/%kernel.environment%');
};
