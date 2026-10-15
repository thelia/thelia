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

use Thelia\Core\SharedCache\SharedCachePurgerFactory;
use Thelia\Core\SharedCache\SharedCachePurgerInterface;

/*
 * Single substitution point for the proxy purge: define
 * SharedCachePurgerInterface from a module configureServices() to talk to
 * another proxy. Declared here and not with #[AsAlias], which Symfony refuses
 * twice for the same id.
 *
 * Nothing is sent until SHARED_CACHE_PURGER names a proxy.
 */
return static function (ContainerConfigurator $configurator): void {
    $configurator->services()
        ->set(SharedCachePurgerInterface::class)
        ->factory([SharedCachePurgerFactory::class, 'create'])
        ->args([
            '%env(SHARED_CACHE_PURGER)%',
            '%env(SHARED_CACHE_PURGE_URLS)%',
            '%env(SHARED_CACHE_PURGE_SECRET)%',
        ]);
};
