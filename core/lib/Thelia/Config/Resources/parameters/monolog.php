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

return static function (ContainerConfigurator $configurator): void {
    $configurator->extension('monolog', [
        'channels' => ['deprecation'],

        'handlers' => [
            'main' => [
                'type' => 'stream',
                'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                'level' => 'debug',
                'channels' => ['!deprecation'],
            ],

            'console' => [
                'type' => 'console',
                'process_psr_3_messages' => false,
                'channels' => ['!event', '!doctrine', '!deprecation'],
            ],

            'deprecations_rotating' => [
                'type' => 'rotating_file',
                'path' => '%kernel.logs_dir%/deprecations-%kernel.environment%.log',
                'level' => 'debug',
                'max_files' => 2,
                'channels' => ['deprecation'],
            ],
        ],
    ]);
};
