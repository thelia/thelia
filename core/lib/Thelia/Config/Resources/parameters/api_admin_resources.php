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
    // API resources a module exposes under /api/admin, as
    // [ResourceClass::class => AdminResources code]. Anything absent from this
    // parameter and from the core map is refused to every non-superadmin admin.
    $container->parameters()
        ->set('thelia.api.admin_resources', []);
};
