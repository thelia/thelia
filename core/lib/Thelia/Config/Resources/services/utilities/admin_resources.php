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

use Thelia\Core\Security\Resource\AdminResources;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->set('thelia.admin.resources', AdminResources::class)
        ->public()
        ->args([param('admin.resources')]);
};
