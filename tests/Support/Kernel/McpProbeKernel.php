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

namespace Thelia\Tests\Support\Kernel;

use App\Kernel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Thelia\Tests\Support\Ai\McpProbeController;

/**
 * The application kernel plus a stand-in route under /api/mcp, behind the same firewall as the MCP endpoint, so the
 * firewall of the MCP tokens is exercised on its own.
 *
 * It compiles into a cache directory of its own: the extra route never reaches the other tests.
 */
final class McpProbeKernel extends Kernel
{
    public const string ROUTE_PATH = '/api/mcp/probe';

    public function getCacheDir(): string
    {
        return parent::getCacheDir().\DIRECTORY_SEPARATOR.'mcp_probe_kernel';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container->withPath(self::applicationKernelFile()));

        $container->services()
            ->set(McpProbeController::class)
            ->autowire()
            ->public()
            ->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        parent::configureRoutes($routes->withPath(self::applicationKernelFile()));

        $routes->add('mcp_probe', self::ROUTE_PATH)->controller(McpProbeController::class);
    }

    private static function applicationKernelFile(): string
    {
        return (string) (new \ReflectionClass(Kernel::class))->getFileName();
    }
}
