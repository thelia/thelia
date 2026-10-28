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
use Thelia\Core\DependencyInjection\McpServerConfiguration;

final class McpServerConfigurationTest extends TestCase
{
    public function testTheDefaultServerOfAFlexRecipeGivesWayToTheOneOfTheCore(): void
    {
        $container = new ContainerBuilder();
        // What the recipe of symfony/mcp-bundle writes in config/packages/mcp.yaml
        $container->prependExtensionConfig('mcp', ['servers' => ['default' => ['registry' => '*', 'http' => ['path' => '/mcp']]]]);

        McpServerConfiguration::applyTo($container);

        $configs = $container->getExtensionConfig('mcp');
        self::assertSame([[], ['servers' => ['default' => McpServerConfiguration::defaultServer()]]], $configs);
        self::assertSame('/api/mcp', $configs[1]['servers']['default']['http']['path']);
    }

    public function testTheOtherServersAndTheClientsOfAShopAreKept(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('mcp', ['servers' => ['default' => ['registry' => '*'], 'partner' => ['registry' => '*']], 'clients' => ['crm' => ['servers' => []]]]);

        McpServerConfiguration::applyTo($container);

        $configs = $container->getExtensionConfig('mcp');
        self::assertSame(['partner' => ['registry' => '*']], $configs[0]['servers']);
        self::assertArrayHasKey('clients', $configs[0]);
        self::assertSame('thelia', $configs[1]['servers']['default']['name']);
    }

    public function testTheCoreServerIsThereWithoutAnyShopConfiguration(): void
    {
        $container = new ContainerBuilder();

        McpServerConfiguration::applyTo($container);

        self::assertSame([['servers' => ['default' => McpServerConfiguration::defaultServer()]]], $container->getExtensionConfig('mcp'));
    }
}
