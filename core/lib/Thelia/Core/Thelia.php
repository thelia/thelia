<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core;

/*
 * Root class of Thelia
 *
 * It extends Symfony\Component\HttpKernel\Kernel for changing some features
 *
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Thelia extends Kernel
{
    use MicroKernelTrait;

    /**
     * Configures the container.
     *
     * You can register extensions:
     *
     *     $c->extension('framework', [
     *         'secret' => '%secret%'
     *     ]);
     *
     * Or services:
     *
     *     $c->services()->set('halloween', 'FooBundle\HalloweenProvider');
     *
     * Or parameters:
     *
     *     $c->parameters()->set('halloween', 'lot of fun');
     */
    protected function configureContainer(ContainerConfigurator $container): void
    {
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }

    /**
     * Gets the cache directory.
     *
     * @return string The cache directory
     *
     * @api
     */
    public function getCacheDir()
    {
        if (\defined('THELIA_ROOT')) {
            return THELIA_CACHE_DIR.$this->environment;
        }

        return parent::getCacheDir();
    }

    /**
     * Gets the log directory.
     *
     * @return string The log directory
     *
     * @api
     */
    public function getLogDir()
    {
        if (\defined('THELIA_ROOT')) {
            return THELIA_LOG_DIR;
        }

        return parent::getLogDir();
    }

    /**
     * Returns the kernel parameters.
     *
     * @return array An array of kernel parameters
     */
    protected function getKernelParameters()
    {
        $parameters = parent::getKernelParameters();

        //Todo replace this by real runtime env
        $parameters['kernel.runtime_environment'] = $this->environment;

        $parameters['thelia.root_dir'] = THELIA_ROOT;
        $parameters['thelia.core_dir'] = \dirname(__DIR__); // This class is in core/lib/Thelia/Core.
        $parameters['thelia.module_dir'] = THELIA_MODULE_DIR;

        return $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function registerBundles(): iterable
    {
        $contents = [
        ];

        if (file_exists(THELIA_ROOT.'config/bundles.php')) {
            $contents = array_merge($contents, require THELIA_ROOT.'config/bundles.php');
        }

        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }
}
