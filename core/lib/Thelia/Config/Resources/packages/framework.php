<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'session' => [
            'save_path' => '%kernel.project_dir%/var/sessions/%kernel.environment%',
        ],
    ]);
};
