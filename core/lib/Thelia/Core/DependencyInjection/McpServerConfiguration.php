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
 * The `default` server of symfony/mcp-bundle belongs to the core: the endpoint sits behind the firewall of the
 * MCP tokens, at a fixed path, with its sessions in a pool of the core.
 *
 * The Flex recipe of the bundle writes a `servers.default` of its own in config/packages/mcp.yaml (path `/mcp`,
 * outside that firewall). The configuration of a bundle merges its sources, so the two would clash (`registry`
 * would hold `*` twice) and, worse, the recipe's path could win. This replaces the `default` server of every
 * source by the one of the core, and keeps the rest of what a shop wrote (other servers, clients).
 *
 * @internal
 */
final class McpServerConfiguration
{
    public const string ALIAS = 'mcp';

    /** The path of the endpoint, the one the `mcp` firewall guards. */
    public const string PATH = '/api/mcp';

    /**
     * @return array<string, mixed>
     */
    public static function defaultServer(): array
    {
        return [
            'name' => 'thelia',
            'registry' => '*',
            'http' => [
                'path' => self::PATH,
            ],
            'session' => [
                'store' => 'cache',
                'cache_pool' => 'thelia.mcp.session_cache',
                'lock' => [
                    'enabled' => true,
                    'factory' => 'lock.factory',
                ],
            ],
        ];
    }

    public static function applyTo(ContainerBuilder $container): void
    {
        $property = new \ReflectionProperty(ContainerBuilder::class, 'extensionConfigs');
        $extensionConfigs = $property->getValue($container);

        $configs = [];

        foreach ($extensionConfigs[self::ALIAS] ?? [] as $config) {
            if (\is_array($config)) {
                unset($config['servers']['default']);

                if ([] === ($config['servers'] ?? null)) {
                    unset($config['servers']);
                }
            }

            $configs[] = $config;
        }

        $configs[] = ['servers' => ['default' => self::defaultServer()]];
        $extensionConfigs[self::ALIAS] = $configs;

        $property->setValue($container, $extensionConfigs);
    }
}
