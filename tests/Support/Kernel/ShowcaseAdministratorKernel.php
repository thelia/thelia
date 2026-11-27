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
use Thelia\Core\Security\Showcase\AdministratorShowcaseAccess;

/**
 * The application kernel with the showcase opened to the administrators of the back office, the way a shop
 * writes it in config/packages/ux_showcase.yaml.
 *
 * It compiles into a cache directory of its own.
 */
final class ShowcaseAdministratorKernel extends Kernel
{
    public function getCacheDir(): string
    {
        return parent::getCacheDir().\DIRECTORY_SEPARATOR.'showcase_administrator_kernel';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container->withPath(self::applicationKernelFile()));

        $container->extension('ux_showcase', ['access' => ['service' => AdministratorShowcaseAccess::class]]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        parent::configureRoutes($routes->withPath(self::applicationKernelFile()));
    }

    /**
     * The application kernel imports the configuration of the shop by paths relative to its own file, which a
     * configurator resolves against the file it is given.
     */
    private static function applicationKernelFile(): string
    {
        return (string) (new \ReflectionClass(Kernel::class))->getFileName();
    }
}
