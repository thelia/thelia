<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('api_platform', [
        'title' => 'API Thelia',
        'version' => '1.0.0',
        'show_webby' => false,
        'serializer' => [
            'hydra_prefix' => true
        ],
        'defaults' => [
            'pagination_client_items_per_page' => true,
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

    if ($container->env() === 'prod') {
        $container->extension('api_platform', [
            'enable_swagger_ui' => false,
            'enable_docs' => false,
            'enable_entrypoint' => false,
        ]);
    }
};
