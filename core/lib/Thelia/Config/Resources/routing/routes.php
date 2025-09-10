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

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Thelia\Controller\Front\DefaultController;

return static function (RoutingConfigurator $routes): void {
    $routes->add('api_front_login_check', '/api/front/login');

    $routes->add('api_admin_login_check', '/api/admin/login');
    $routes->add('index', '/')
        ->controller([DefaultController::class, 'indexAction'])
        ->methods(['GET']);

    $routes->import('.', 'module_attribute');
    $routes->import('.', 'template_attribute');
    $routes->import('.', 'module_annotation');
    $routes->import('.', 'module_xml');
};
