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
    $container->extension('api_platform', [
        'title' => 'API Thelia',
        'version' => '1.0.0',
        'show_webby' => false,
        'serializer' => [
            'hydra_prefix' => true,
        ],
        'defaults' => [
            'pagination_client_items_per_page' => true,
            'stateless' => false,
        ],
        'mapping' => [
            'paths' => [],
        ],
        'formats' => [
            'json' => [
                'mime_types' => ['application/json'],
            ],
            'jsonld' => [
                'mime_types' => ['application/ld+json'],
            ],
            'html' => [
                'mime_types' => ['text/html'],
            ],
        ],
        'swagger' => [
            'versions' => [3],
            'swagger_ui_extra_configuration' => [
                'docExpansion' => 'none',
                'filter' => true,
                'persistAuthorization' => true,
                'showCommonExtensions' => true,
            ],
            'api_keys' => [
                'JWT' => [
                    'name' => 'Authorization',
                    'type' => 'header',
                ],
            ],
        ],
    ]);
};
