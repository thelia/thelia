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

use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\RouteCollection;
use Thelia\Core\Template\TemplateService;

class TemplateAttributeLoader extends Loader
{
    private bool $isLoaded = false;

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw new \RuntimeException('Do not add the "template_attribute" loader twice');
        }

        $fileLocator = new FileLocator();
        $loader = new AttributeDirectoryLoader($fileLocator, new AttributeRouteControllerLoader($this->env));

        $routes = new RouteCollection();

        $templates = TemplateService::getTemplatesAbsolutePathWithParents();

        foreach ($templates as $templateChain) {
            // Furthest ancestor first: a collection added later overrides the routes of the
            // same name added before it, and it is the template the shop actually runs -
            // the nearest one - whose controllers must win.
            foreach (array_reverse($templateChain) as $templatePath) {
                $templateControllerPath = $templatePath.\DIRECTORY_SEPARATOR.'src';

                if (!is_dir($templateControllerPath)) {
                    continue;
                }

                $templateRoutes = $loader->load($templateControllerPath, 'attribute');

                if (!$templateRoutes instanceof RouteCollection) {
                    continue;
                }

                $routes->addCollection($templateRoutes);
            }
        }

        foreach ($routes as $route) {
            $route->setPath('/'.$route->getPath());
        }

        $this->isLoaded = true;

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'template_attribute' === $type;
    }
}
