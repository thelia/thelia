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

use Thelia\Core\Security\SecurityContext;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->alias('thelia.securityContext', SecurityContext::class)
        ->public();
};
