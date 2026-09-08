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

namespace Thelia\Core\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The logging a shop gets when it has not written its own.
 *
 * These are defaults, so they are prepended, and they are prepended in groups:
 * a group holding a handler the application names is dropped whole.
 *
 * Merging into a handler the application named would be worse than useless,
 * because most handler options belong to one handler type - a shop turning
 * "main" into a plain stream would inherit `action_level` and
 * `excluded_http_codes` from the fingers-crossed default below, and the
 * configuration would be refused. And a group is the unit rather than a
 * handler because a buffering handler is nothing without the handler it writes
 * through: leaving "main_stream" behind, once a shop has written its own
 * "main", would put a second handler on the same file.
 */
final class LoggingDefaults
{
    public static function prependTo(ContainerBuilder $container): void
    {
        $handlers = [];
        $named = self::handlersOfTheApplication($container);

        foreach (self::handlerGroups() as $group) {
            if ([] === array_intersect_key($group, $named)) {
                $handlers += $group;
            }
        }

        $container->prependExtensionConfig('monolog', [
            'channels' => ['deprecation', 'security'],
            'handlers' => $handlers,
        ]);
    }

    /**
     * @return list<array<string, array<string, mixed>>>
     */
    private static function handlerGroups(): array
    {
        return [[
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
        ], [
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
        ], [
            'console' => [
                'type' => 'console',
                'process_psr_3_messages' => false,
                'channels' => ['!event', '!doctrine', '!deprecation'],
            ],
        ], [
            'deprecations_rotating' => [
                'type' => 'rotating_file',
                'path' => '%kernel.logs_dir%/deprecations-%kernel.environment%.log',
                'level' => 'debug',
                'max_files' => 2,
                'channels' => ['deprecation'],
            ],
        ]];
    }

    /**
     * @return array<string, true> the handler names the application has declared
     */
    private static function handlersOfTheApplication(ContainerBuilder $container): array
    {
        $names = [];

        foreach ($container->getExtensionConfig('monolog') as $configuration) {
            foreach (array_keys($configuration['handlers'] ?? []) as $name) {
                $names[$name] = true;
            }
        }

        return $names;
    }
}
