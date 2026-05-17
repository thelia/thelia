<?php

declare(strict_types=1);

namespace BackOfficeDefaultBundle\Routing;

use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\RouteCollection;

final class BackOfficeDefaultAttributeLoader extends Loader
{
    public const ROUTE_TYPE = 'bo_default_attribute';

    private bool $isLoaded = false;

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw new \RuntimeException(\sprintf('Do not add the "%s" loader twice.', self::ROUTE_TYPE));
        }

        $routes = new RouteCollection();
        $controllerPath = \dirname(__DIR__).\DIRECTORY_SEPARATOR.'Controller';

        if (is_dir($controllerPath)) {
            $loader = new AttributeDirectoryLoader(new FileLocator(), new AttributeRouteControllerLoader($this->env));
            $loaded = $loader->load($controllerPath, 'attribute');

            if ($loaded instanceof RouteCollection) {
                $routes->addCollection($loaded);
            }
        }

        $this->isLoaded = true;

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return self::ROUTE_TYPE === $type;
    }
}
