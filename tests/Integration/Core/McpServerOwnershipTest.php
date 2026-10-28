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

namespace Thelia\Tests\Integration\Core;

use Thelia\Core\DependencyInjection\McpServerConfiguration;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Kernel\McpModuleServerKernel;

/**
 * The MCP server belongs to the core: what a module writes about the `mcp` extension is read before the server of
 * the core is imposed, so it cannot move it. Another server of a module over HTTP is for the compilation to refuse.
 */
final class McpServerOwnershipTest extends IntegrationTestCase
{
    public function testTheServerOfTheCoreIsImposedAfterTheConfigurationOfTheModules(): void
    {
        $moved = ['default' => ['registry' => '*', 'http' => ['path' => '/module/mcp']]];

        $configs = (new McpModuleServerKernel($moved))->containerBuilder()->getExtensionConfig(McpServerConfiguration::ALIAS);

        self::assertSame(['default' => McpServerConfiguration::defaultServer()], end($configs)['servers']);
        self::assertSame([], $configs[array_key_last($configs) - 1]['servers'] ?? [], 'what the module wrote about the default server is dropped');
    }

    public function testAnotherServerOfAModuleOverHttpFailsTheCompilation(): void
    {
        $kernel = new McpModuleServerKernel(['module' => ['registry' => '*', 'http' => ['path' => '/module/other']]]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('found "module"');

        $kernel->boot();
    }
}
