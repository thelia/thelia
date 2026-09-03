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
        'channels' => ['deprecation', 'security'],

        'handlers' => [
            'main' => [
                'type' => 'fingers_crossed',
                'action_level' => 'error',
                'handler' => 'main_stream',
                'excluded_http_codes' => [404, 405],
            ],
            'main_stream' => [
                'type' => 'rotating_file',
                'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                'level' => 'debug',
                'max_files' => 7,
                'channels' => ['!deprecation'],
            ],
            // Refused authentications get a file of their own, and get there
            // whatever else happens. The main handler only opens its buffer
            // when something errors, so a warning on its own never reaches the
            // disk, which is exactly the shape a run of failed logins has. A
            // separate file is also what a log watcher wants to be pointed at,
            // and it is kept longer: an attempt spread over weeks is only
            // visible if weeks are still on disk.
            //
            // The channel is deliberately left in the main log too, rather
            // than excluded from it. Symfony's security component narrates its
            // authenticators on this channel in debug and info, and that
            // narration is how a rejected token or a misrouted firewall gets
            // diagnosed: it has to stay next to the request that carried it. A
            // warning is written twice whenever the main handler opens its
            // buffer, which costs a duplicated line and keeps the context.
            'security_rotating' => [
                'type' => 'rotating_file',
                'path' => '%kernel.logs_dir%/security-%kernel.environment%.log',
                'level' => 'warning',
                'max_files' => 30,
                'channels' => ['security'],
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
