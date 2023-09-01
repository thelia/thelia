<?php

namespace Thelia\Api\Bridge\Propel\OpenApiDecorator;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\OpenApi;

class HideExtendDecorator implements OpenApiFactoryInterface
{
    private $decorated;
    public function __construct(OpenApiFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = $this->decorated->__invoke($context);
        $paths = $openApi->getPaths()->getPaths();
        $filteredPaths = new Paths();
        foreach ($paths as $path => $pathItem) {
            // If a prefix is configured on API Platform's routes, it must appear here.
            if ($path === '/weathers/{id}') {
                continue;
            }
            $filteredPaths->addPath($path, $pathItem);
        }
        return $openApi->withPaths($filteredPaths);
    }
}
