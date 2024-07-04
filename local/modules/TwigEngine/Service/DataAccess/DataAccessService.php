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

namespace TwigEngine\Service\DataAccess;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\RouterInterface;
use TwigEngine\Service\ApiPlatformMetadataService;

readonly class DataAccessService
{
    public function __construct(
        private RouterInterface $router,
        private ApiPlatformMetadataService $apiPlatformMetadataService,
        private LoopDataAccessService $loopDataAccessService,
    ) {
    }

    public function resources(string $path, array $parameters = []): object|array
    {
        $route = $this->router->match($path);

        $resourceClass = $route['_api_resource_class'];
        $routeName = $route['_route'];

        $operation = $this->apiPlatformMetadataService->getOperation(
            $resourceClass,
            $routeName
        );
        if ($operation === null) {
            throw new \RuntimeException('Operation not found');
        }

        $context = [
            'operation' => $operation,
            'uri_variables' => [],
            'resource_class' => $resourceClass,
            'filters' => $parameters,
            'groups' => $operation->getNormalizationContext()['groups'] ?? null,
        ];

        if (
            !$this->apiPlatformMetadataService->canUserAccessResource(
                $resourceClass,
                $path,
                Request::METHOD_GET,
                $operation,
                $context
            )
        ) {
            throw new AccessDeniedHttpException('Access Denied');
        }

        return $this->apiPlatformMetadataService->getProvider($operation)->provide(
            $operation,
            [],
            $context
        );
    }

    /** @deprecated use new data access layer */
    public function loop(string $loopName, string $loopType, array $params = []): array
    {
        return $this->loopDataAccessService->theliaLoop($loopName, $loopType, $params);
    }

    /** @deprecated use new data access layer */
    public function loopCount(string $loopType, array $params = []): int
    {
        return $this->loopDataAccessService->theliaCount($loopType, $params);
    }
}
