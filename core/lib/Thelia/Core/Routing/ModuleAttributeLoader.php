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

use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\RouteCollection;
use Thelia\Model\ModuleQuery;

class ModuleAttributeLoader extends Loader
{
    private bool $isLoaded = false;

    public function load($resource, string $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw new \RuntimeException('Do not add the "module_attribute" loader twice');
        }
        $fileLocator = new FileLocator();
        $loader = new AttributeDirectoryLoader($fileLocator, new AttributeRouteControllerLoader($this->env));

        $routes = new RouteCollection();

        $modules = ModuleQuery::create()
            ->filterByActivate(true)
            ->find();

        $coreControllerPath = THELIA_LIB.'Controller';
        $routes->addCollection($loader->load($coreControllerPath, 'attribute'));

        foreach ($modules as $module) {
            $moduleControllerPath = $module->getAbsoluteBaseDir().\DIRECTORY_SEPARATOR.'Controller';

            if (!is_dir($moduleControllerPath)) {
                continue;
            }

            $moduleRoutes = $loader->load($moduleControllerPath, 'attribute');
            if (!$moduleRoutes instanceof RouteCollection) {
                continue;
            }
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
        return 'module_attribute' === $type;
    }
}
