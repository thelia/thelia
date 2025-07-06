<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Thelia\Core\Security\SecurityContext;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->alias('thelia.securityContext', SecurityContext::class)
        ->public();
};
