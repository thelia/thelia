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

use Thelia\Core\EventListener\ControllerListener;
use Thelia\Core\EventListener\ErrorListener;
use Thelia\Core\EventListener\ResponseListener;
use Thelia\Core\EventListener\SessionListener;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->alias('response.listener', ResponseListener::class);

    $services->alias('session.listener', SessionListener::class);

    $services->alias('controller.listener', ControllerListener::class);

    $services->alias('error.listener', ErrorListener::class);
};
