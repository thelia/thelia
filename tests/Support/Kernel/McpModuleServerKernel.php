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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as HttpKernel;
use Thelia\Core\TheliaKernel;

/**
 * Builds the container of the kernel the way a boot does, with an activated module that writes the given servers in
 * the configuration of the `mcp` extension, which is what a module's loadConfiguration() may do.
 *
 * It compiles into a cache directory of its own.
 */
final class McpModuleServerKernel extends Kernel
{
    /**
     * @param array<string, array<string, mixed>> $moduleServers the `servers` a module writes for the `mcp` extension
     */
    public function __construct(private readonly array $moduleServers)
    {
        parent::__construct('test', false);
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().\DIRECTORY_SEPARATOR.'mcp_module_server_kernel';
    }

    public function containerBuilder(): ContainerBuilder
    {
        (new \ReflectionMethod(TheliaKernel::class, 'initializePropel'))->invoke($this);
        (new \ReflectionMethod(HttpKernel::class, 'initializeBundles'))->invoke($this);

        return $this->buildContainer();
    }

    protected function loadModulesConfiguration(ContainerBuilder $container, iterable $modules): void
    {
        parent::loadModulesConfiguration($container, $modules);

        $container->loadFromExtension('mcp', ['servers' => $this->moduleServers]);
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container->withPath((string) (new \ReflectionClass(Kernel::class))->getFileName()));
    }
}
