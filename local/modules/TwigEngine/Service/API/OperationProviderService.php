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

namespace TwigEngine\Service\API;

use ApiPlatform\Metadata\Exception\InvalidIdentifierException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\UriVariablesResolverTrait;

class OperationProviderService
{
    use UriVariablesResolverTrait;

    public function getOperation(MetadataService $metadataService, array $route): Operation
    {
        $resourceClass = $route['_api_resource_class'];
        $routeName = $route['_route'];

        $operation = $metadataService->getOperation($resourceClass, $routeName);
        if ($operation === null) {
            throw new \RuntimeException('Operation not found');
        }

        return $operation;
    }

    /**
     * @throws InvalidIdentifierException
     */
    public function getUriVariables(array $route, Operation $operation, string $resourceClass): array
    {
        $id = isset($route['id']) ? ['id' => $route['id']] : [];

        return $this->getOperationUriVariables($operation, $id, $resourceClass);
    }
}
