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
use Thelia\Tests\Support\Routing\RoutingConditionProbe;

/**
 * The application kernel plus one route whose condition calls a service, the
 * way a module declares one.
 *
 * It compiles into a cache directory of its own, so the extra route never
 * reaches the container and the routes the other tests run against.
 */
final class RoutingConditionKernel extends Kernel
{
    public const string ROUTE_PATH = '/routing-condition-probe';

    public function getCacheDir(): string
    {
        return parent::getCacheDir().\DIRECTORY_SEPARATOR.'routing_condition_kernel';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container->withPath(self::applicationKernelFile()));

        $container->services()
            ->set(RoutingConditionProbe::class)
            ->public()
            ->tag('routing.condition_service', ['alias' => RoutingConditionProbe::ALIAS])
            ->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        parent::configureRoutes($routes->withPath(self::applicationKernelFile()));

        $routes->add('routing_condition_probe', self::ROUTE_PATH)
            ->controller(RoutingConditionProbe::class)
            ->condition(\sprintf("service('%s').allows(request)", RoutingConditionProbe::ALIAS));
    }

    /**
     * The application kernel imports the configuration of the shop by paths
     * relative to its own file, which a configurator resolves against the file
     * of the running kernel class: handing it that file keeps them pointing at
     * the shop.
     */
    private static function applicationKernelFile(): string
    {
        return (string) (new \ReflectionClass(Kernel::class))->getFileName();
    }
}
