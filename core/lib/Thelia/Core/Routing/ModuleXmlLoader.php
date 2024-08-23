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

namespace Thelia\Core\Routing;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Loader\XmlFileLoader;
use Symfony\Component\Routing\RouteCollection;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;

class ModuleXmlLoader extends Loader
{
    private bool $isLoaded = false;

    public function __construct(
        string $env = null
    ) {
        parent::__construct($env);
    }

    public function load($resource, string $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw new \RuntimeException('Do not add the "module_xml" loader twice');
        }
        $routes = new RouteCollection();
        if (\defined('THELIA_INSTALL_MODE')) {
            return $routes;
        }
        $fileLocator = new FileLocator();
        $loader = new XmlFileLoader($fileLocator, $this->env);

        $modules = ModuleQuery::getActivated();

        /** @var Module $module */
        foreach ($modules as $module) {
            $routingConfigFilePath = $module->getAbsoluteBaseDir().DS.'Config'.DS.'routing.xml';

            if (!file_exists($routingConfigFilePath)) {
                continue;
            }

            $moduleRoutes = $loader->load($routingConfigFilePath);
            $moduleRoutePrefix = \call_user_func([$module->getFullNamespace(), 'getRoutePrefix']);

            foreach ($moduleRoutes->all() as $moduleRoute) {
                $moduleRoute->setPath($moduleRoutePrefix.$moduleRoute->getPath());
            }

            $routes->addCollection($moduleRoutes);
        }

        foreach ($routes as $route) {
            $route->setPath('/'.$route->getPath());
        }
        $this->isLoaded = true;

        return $routes;
    }

    public function supports($resource, string $type = null): bool
    {
        return 'module_xml' === $type;
    }
}
