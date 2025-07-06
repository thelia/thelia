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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\Hook\HookHelper;
use Thelia\Mailer\MailerFactory;
use Thelia\TaxEngine\TaxEngine;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->alias('thelia.taxEngine', TaxEngine::class);
    $services->alias('thelia.hookHelper', HookHelper::class);
    $services->alias('mailer', MailerFactory::class);
    $services->alias(ContainerInterface::class, 'service_container');
    $services->alias(\Psr\Container\ContainerInterface::class, 'service_container');
};
