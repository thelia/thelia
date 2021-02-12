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

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Bundle\FrameworkBundle\Routing\AnnotatedRouteControllerLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router as BaseRouter;
use Thelia\Model\ModuleQuery;

class AnnotationRouter extends BaseRouter
{
    /** @var FileLocator */
    protected $fileLocator;

    public function __construct(
        FileLocator $fileLocator
    ) {
        $this->fileLocator = $fileLocator;

        // Load default options
        $this->setOptions([]);
    }

    public function getRouteCollection()
    {
        $loader = new AnnotationDirectoryLoader($this->fileLocator, new AnnotatedRouteControllerLoader(new AnnotationReader()));

        $routes = new RouteCollection();

        $modules = ModuleQuery::create()
            ->filterByActivate(true)
            ->find();

        $coreControllerPath = THELIA_LIB.'Controller';
        $routes->addCollection(
            $loader->load($coreControllerPath, 'annotation')
        );

        foreach ($modules as $module) {
            $moduleControllerPath = $module->getAbsoluteConfigPath().DS.'..'.DS.'Controller';

            if (!is_dir($moduleControllerPath)) {
                continue;
            }

            $routes->addCollection(
                $loader->load($moduleControllerPath, 'annotation')
            );
        }

        foreach ($routes as $route) {
            $route->setPath('/'.$route->getPath());
        }

        return $routes;
    }
}
