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

namespace Thelia\Core\Routing;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Bundle\FrameworkBundle\Routing\AnnotatedRouteControllerLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\RouteCollection;
use Thelia\Model\ModuleQuery;

class ModuleAnnotationLoader extends Loader
{
    private bool $isLoaded = false;

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw new \RuntimeException('Do not add the "module_annotation" loader twice');
        }

        $annotationReader = new AnnotationReader();
        $loader = new AnnotatedRouteControllerLoader($annotationReader);
        $routes = new RouteCollection();

        $modules = ModuleQuery::create()
            ->filterByActivate(true)
            ->find();

        foreach ($modules as $module) {
            $moduleControllerPath = $module->getAbsoluteBaseDir().\DIRECTORY_SEPARATOR.'Controller';

            if (!is_dir($moduleControllerPath)) {
                continue;
            }

            $fileLocator = new FileLocator($moduleControllerPath);
            $routeLoader = new AnnotationDirectoryLoader($fileLocator, $loader);
            $moduleRoutes = $routeLoader->load('.', 'annotation');

            if (!$moduleRoutes instanceof RouteCollection) {
                continue;
            }

            $moduleRoutePrefix = \call_user_func([$module->getFullNamespace(), 'getAnnotationRoutePrefix']);

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

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'module_annotation' === $type;
    }
}
