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
use Thelia\Controller\Admin\AdminController;
use Thelia\Controller\Admin\SessionController;

return static function (RoutingConfigurator $routes): void {
    $routes->add('admin', '/admin')
        ->controller([AdminController::class, 'indexAction'])
        ->defaults(['not-logged' => '1']);

    $routes->add('admin.home', '/admin/home')
        ->controller([AdminController::class, 'indexAction']);

    $routes->add('admin.login', '/admin/login')
        ->controller([SessionController::class, 'showLoginAction'])
        ->defaults(['not-logged' => '1']);

    $routes->add('admin.lost-password', '/admin/lost-password')
        ->controller([SessionController::class, 'showLostPasswordAction'])
        ->defaults(['not-logged' => '1']);

    $routes->add('admin.password-create', '/admin/password-create-request')
        ->controller([SessionController::class, 'passwordCreateRequestAction'])
        ->methods(['POST'])
        ->defaults(['not-logged' => '1']);

    $routes->add('admin.password-create-success', '/admin/password-create-request-success')
        ->controller([SessionController::class, 'passwordCreateRequestSuccessAction'])
        ->defaults(['not-logged' => '1']);

    $routes->add('admin.password-create-form', '/admin/password-create/{token}')
        ->controller([SessionController::class, 'displayCreateFormAction'])
        ->requirements(['token' => '.*'])
        ->defaults(['not-logged' => '1']);

    $routes->add('admin.password-renewed', '/admin/password-created')
        ->controller([SessionController::class, 'passwordCreatedAction'])
        ->defaults(['not-logged' => '1']);

    $routes->add('admin.password-renewed-success', '/admin/password-create-success')
        ->controller([SessionController::class, 'passwordCreatedSuccessAction'])
        ->defaults(['not-logged' => '1']);

    $routes->add('admin.logout', '/admin/logout')
        ->controller([SessionController::class, 'checkLogoutAction']);

    $routes->add('admin.checklogin', '/admin/checklogin')
        ->controller([SessionController::class, 'checkLoginAction'])
        ->defaults(['not-logged' => '1']);
};
