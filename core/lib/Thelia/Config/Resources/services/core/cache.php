<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(AdapterInterface::class, FilesystemAdapter::class)
        ->public()
        ->args([
            '%thelia.cache.namespace%',
            '600',
            '%kernel.cache_dir%'
        ]);

    $services->alias('thelia.cache', AdapterInterface::class)
        ->public();
};
