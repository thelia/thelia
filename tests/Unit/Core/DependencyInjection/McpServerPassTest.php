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

namespace Thelia\Tests\Unit\Core\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Thelia\Core\DependencyInjection\Compiler\McpServerPass;

final class McpServerPassTest extends TestCase
{
    public function testTheCoreServerAloneOnItsPathCompiles(): void
    {
        $this->expectNotToPerformAssertions();

        (new McpServerPass())->process($this->containerServing(['default' => '/api/mcp']));
    }

    public function testAContainerWithoutTheBundleIsLeftAlone(): void
    {
        $this->expectNotToPerformAssertions();

        (new McpServerPass())->process(new ContainerBuilder());
    }

    public function testAnotherServerOverHttpFailsTheCompilationAndNamesIt(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('found "partner"');

        (new McpServerPass())->process($this->containerServing(['default' => '/api/mcp', 'partner' => '/mcp/partner']));
    }

    public function testTheCoreServerOffItsPathFailsTheCompilation(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must be served over HTTP at /api/mcp');

        (new McpServerPass())->process($this->containerServing(['default' => '/mcp']));
    }

    public function testTheCoreServerWithoutHttpFailsTheCompilation(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('found no HTTP transport');

        (new McpServerPass())->process($this->containerServing([]));
    }

    /**
     * @param array<string, string> $paths the HTTP path of each server, by name
     */
    private function containerServing(array $paths): ContainerBuilder
    {
        $routes = [];

        foreach ($paths as $name => $path) {
            $routes[] = ['name' => $name, 'path' => $path, 'controller' => \sprintf('mcp.server.%s.controller::handle', $name)];
        }

        $container = new ContainerBuilder();
        $container->setDefinition(McpServerPass::ROUTE_LOADER, new Definition(\stdClass::class, [$routes]));

        return $container;
    }
}
