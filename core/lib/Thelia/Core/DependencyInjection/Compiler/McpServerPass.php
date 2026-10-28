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

namespace Thelia\Core\DependencyInjection\Compiler;

use Mcp\Schema\ServerCapabilities;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Thelia\Core\DependencyInjection\McpServerConfiguration;

/**
 * Finishes the MCP server of symfony/mcp-bundle, which the core owns: the bundle gives no way to say it in its
 * configuration.
 *
 * - The server announces tools and nothing else (`listChanged` false: no stream, no notification), instead of
 *   the capabilities the SDK deduces from its registry.
 * - The hosts of the DNS rebinding protection come from THELIA_MCP_ALLOWED_HOSTS at run time: the node of the
 *   bundle refuses an environment variable.
 *
 * It also refuses a container where the core is not the only MCP server over HTTP: the `default` server must sit on
 * `/api/mcp`, the path of the token firewall, and no other server may answer a path of its own (it would reach the
 * tools of the registry, and the methods the core does not serve, outside that firewall).
 *
 * Nothing happens when the bundle is not registered.
 */
final class McpServerPass implements CompilerPassInterface
{
    public const string SERVER = 'default';

    public const string ROUTE_LOADER = 'mcp.server.route_loader';

    public function process(ContainerBuilder $container): void
    {
        $this->assertTheCoreIsTheOnlyHttpServer($container);

        $builder = \sprintf('mcp.server.%s.builder', self::SERVER);
        $middleware = \sprintf('mcp.server.%s.middleware_factory', self::SERVER);

        if ($container->hasDefinition($builder)) {
            $container->getDefinition($builder)->addMethodCall('setCapabilities', [
                new Definition(ServerCapabilities::class, [
                    '$tools' => true,
                    '$toolsListChanged' => false,
                    '$resources' => false,
                    '$resourcesSubscribe' => false,
                    '$resourcesListChanged' => false,
                    '$prompts' => false,
                    '$promptsListChanged' => false,
                    '$logging' => false,
                    '$completions' => false,
                ]),
            ]);
        }

        if ($container->hasDefinition($middleware)) {
            $container->getDefinition($middleware)->replaceArgument(0, '%env(csv:THELIA_MCP_ALLOWED_HOSTS)%');
        }
    }

    private function assertTheCoreIsTheOnlyHttpServer(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::ROUTE_LOADER)) {
            return;
        }

        $servers = [];

        /** @var array{name: string, path: string} $route */
        foreach ($container->getDefinition(self::ROUTE_LOADER)->getArgument(0) as $route) {
            $servers[$route['name']] = $route['path'];
        }

        $others = array_diff_key($servers, [self::SERVER => true]);

        if ([] !== $others) {
            throw new \LogicException(\sprintf('The MCP server of Thelia is the "%s" server, served at %s behind the firewall of the MCP tokens: no other MCP server may be served over HTTP, found "%s". Remove it from the configuration of the "mcp" extension (a module, config/packages/mcp.yaml), or serve it over stdio.', self::SERVER, McpServerConfiguration::PATH, implode('", "', array_keys($others))));
        }

        if (($servers[self::SERVER] ?? null) !== McpServerConfiguration::PATH) {
            throw new \LogicException(\sprintf('The "%s" MCP server of Thelia must be served over HTTP at %s, behind the firewall of the MCP tokens, found %s.', self::SERVER, McpServerConfiguration::PATH, $servers[self::SERVER] ?? 'no HTTP transport'));
        }
    }
}
