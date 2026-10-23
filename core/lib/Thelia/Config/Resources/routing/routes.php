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
use Thelia\Controller\Front\PublicationPreviewController;
use Thelia\Domain\CMS\Publication\Preview\PublicationPreview;

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

    // The preview of a content or a folder whatever its publication state, for an
    // administrator or whoever holds the signed link the back office hands out.
    $routes->add(PublicationPreview::ROUTE, '/preview/{type}/{id}')
        ->controller(PublicationPreviewController::class)
        ->requirements(['type' => 'content|folder', 'id' => '\d+'])
        ->methods(['GET']);

    $routes->import('.', 'module_attribute');
    $routes->import('.', 'template_attribute');
    $routes->import('.', 'module_xml');
};
