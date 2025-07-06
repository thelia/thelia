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

use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Thelia\Core\Controller\ControllerResolver;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->set(ControllerResolver::class)
        ->args([service('service_container')]);

    $services->alias('controller_resolver', ControllerResolver::class);

    $services->alias(ControllerResolverInterface::class, ControllerResolver::class);
};
