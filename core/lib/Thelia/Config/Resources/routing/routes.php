<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Thelia\Controller\Front\DefaultController;

return static function (RoutingConfigurator $routes): void {
    $routes->add('index', '/')
        ->controller([DefaultController::class, 'noAction']);

    $routes->add('api_front_login_check', '/api/front/login');

    $routes->add('api_admin_login_check', '/api/admin/login');

    $routes->import('.', 'module_attribute');
    $routes->import('.', 'template_attribute');
    $routes->import('.', 'module_annotation');
    $routes->import('.', 'module_xml');
};
