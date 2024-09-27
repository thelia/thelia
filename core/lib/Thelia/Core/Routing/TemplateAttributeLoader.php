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
use Thelia\Service\TemplateService;

class TemplateAttributeLoader extends Loader
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

        $templates = TemplateService::getTemplatesAbsolutePath();

        foreach ($templates as $templatePath) {
            $templateControllerPath = $templatePath.\DIRECTORY_SEPARATOR.'src';

            if (!is_dir($templateControllerPath)) {
                continue;
            }

            $templateRoutes = $loader->load($templateControllerPath, 'attribute');
            if (!$templateRoutes instanceof RouteCollection) {
                continue;
            }

            foreach ($templateRoutes->all() as $templateRoute) {
                $templateRoute->setPath($templateRoute->getPath());
            }

            $routes->addCollection($templateRoutes);
        }

        foreach ($routes as $route) {
            $route->setPath('/'.$route->getPath());
        }
        $this->isLoaded = true;

        return $routes;
    }

    public function supports($resource, string $type = null): bool
    {
        return 'template_attribute' === $type;
    }
}
