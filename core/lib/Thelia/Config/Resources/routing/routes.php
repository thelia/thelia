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
use Thelia\Controller\Api\RefreshTokenController;
use Thelia\Controller\Front\ContactController;
use Thelia\Controller\Front\DefaultController;
use Thelia\Controller\Front\ExpressCheckoutController;

return static function (RoutingConfigurator $routes): void {
    $routes->add('api_front_login_check', '/api/front/login');

    $routes->add('api_admin_login_check', '/api/admin/login');

    $routes->add('api_admin_token_refresh', '/api/admin/token/refresh')
        ->controller([RefreshTokenController::class, 'refreshAdmin'])
        ->methods(['POST']);

    $routes->add('api_front_token_refresh', '/api/front/token/refresh')
        ->controller([RefreshTokenController::class, 'refreshFront'])
        ->methods(['POST']);

    $routes->add('index', '/')
        ->controller([DefaultController::class, 'noAction']);

    // Declared before the theme and module routes: a front-office theme serves its pages
    // through a catch-all that matches any single segment whatever the method, so a route
    // imported after it would never be reached. The GET of the same path stays with the
    // theme, which owns the contact page and its markup.
    $routes->add('contact_submit', '/contact')
        ->controller([ContactController::class, 'send'])
        ->methods(['POST']);

    // Owned by the core rather than by each wallet module: this is where the buyer is put
    // in the session and the cart handed to them, and doing that in the wrong order
    // deletes the cart. A module only reads its provider's answer.
    $routes->add('express_checkout_confirm', '/checkout/express/{moduleCode}/confirm')
        ->controller([ExpressCheckoutController::class, 'confirm'])
        ->requirements(['moduleCode' => '[A-Za-z0-9_]+'])
        ->methods(['POST']);

    $routes->add('express_checkout_amount', '/checkout/express/{moduleCode}/amount')
        ->controller([ExpressCheckoutController::class, 'amount'])
        ->requirements(['moduleCode' => '[A-Za-z0-9_]+'])
        ->methods(['POST']);

    $routes->import('.', 'module_attribute');
    $routes->import('.', 'template_attribute');
    $routes->import('.', 'module_xml');
};
