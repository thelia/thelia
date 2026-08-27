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
    $swaggerUi = 'bundles'.\DIRECTORY_SEPARATOR.'ApiPlatformBundle'
        .\DIRECTORY_SEPARATOR.'SwaggerUi'.\DIRECTORY_SEPARATOR.'index.html.twig';

    // API Platform renders its documentation page from a template name it
    // hardcodes, so dressing that page as Thelia means adding a path to the
    // bundle's Twig namespace. Twig reads the paths declared here before the
    // ones the bundle hierarchy builds, which would take the shop's own
    // templates/bundles/ApiPlatformBundle override away from it: the shop
    // wins by shipping that template, and Thelia then steps aside.
    //
    // The check runs when the container is built, so a shop adding the file
    // to an already warmed cache has to clear it.
    if (\defined('THELIA_TEMPLATE_DIR') && is_file(THELIA_TEMPLATE_DIR.$swaggerUi)) {
        return;
    }

    $container->extension('twig', [
        'paths' => [
            \dirname(__DIR__, 3).\DIRECTORY_SEPARATOR.'Resources'
                .\DIRECTORY_SEPARATOR.'views'.\DIRECTORY_SEPARATOR.'ApiPlatform' => 'ApiPlatform',
        ],
    ]);
};
