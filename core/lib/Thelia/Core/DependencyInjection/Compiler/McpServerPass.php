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

/**
 * Finishes the MCP server of symfony/mcp-bundle, which the core owns: the bundle gives no way to say it in its
 * configuration.
 *
 * - The server announces tools and nothing else (`listChanged` false: no stream, no notification), instead of
 *   the capabilities the SDK deduces from its registry.
 * - The hosts of the DNS rebinding protection come from THELIA_MCP_ALLOWED_HOSTS at run time: the node of the
 *   bundle refuses an environment variable.
 *
 * Nothing happens when the bundle is not registered, or when a shop removed its `default` server.
 */
final class McpServerPass implements CompilerPassInterface
{
    public const string SERVER = 'default';

    public function process(ContainerBuilder $container): void
    {
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
}
