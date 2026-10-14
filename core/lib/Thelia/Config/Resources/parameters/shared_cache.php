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

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Thelia\Core\SharedCache\SharedCacheKeyCollector;
use Thelia\Core\SharedCache\SharedCachePolicy;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        ->set('env(SHARED_CACHE_PURGER)', '')
        ->set('env(SHARED_CACHE_PURGE_URLS)', '')
        ->set('env(SHARED_CACHE_PURGE_SECRET)', '')
        ->set('thelia.shared_cache.readable_prefixes', SharedCacheKeyCollector::READABLE_PREFIXES)
        ->set('thelia.shared_cache.never_shared_prefixes', SharedCachePolicy::SENSITIVE_PREFIXES);
};
