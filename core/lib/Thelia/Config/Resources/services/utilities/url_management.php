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

use Thelia\Tools\URL;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    // URL management
    $services->set(URL::class)
        ->args([service('router.admin')]);

    $services->alias('thelia.url.manager', URL::class)
        ->public();
};
