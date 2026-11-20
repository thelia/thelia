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

namespace Thelia\Api\Routing;

use ApiPlatform\Metadata\Exception\OperationNotFoundException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\RequestPath;

/**
 * Tells which API Platform operation a request was routed to.
 *
 * API Platform puts "_api_operation" on the request from its own listeners, and only
 * when "use_symfony_listeners" is on: without it, or before those listeners ran, what
 * the router leaves is the resource class and the operation name, which is enough to
 * look the operation up. Everything that needs the operation of the current request
 * asks here instead of reading the attribute.
 */
final readonly class RequestOperationResolver
{
    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
    ) {
    }

    public function find(Request $request): ?Operation
    {
        $operation = $request->attributes->get('_api_operation');

        if ($operation instanceof Operation) {
            return $operation;
        }

        $resourceClass = $request->attributes->get('_api_resource_class');
        $operationName = $request->attributes->get('_api_operation_name');

        if (!\is_string($resourceClass) || !\is_string($operationName)) {
            return null;
        }

        try {
            return $this->resourceMetadataCollectionFactory->create($resourceClass)->getOperation($operationName);
        } catch (OperationNotFoundException) {
            return null;
        }
    }

    /**
     * @throws \LogicException when the request does not belong to an API Platform operation
     */
    public function resolve(Request $request): Operation
    {
        return $this->find($request) ?? throw new \LogicException(\sprintf('No API Platform operation matches the request "%s %s": neither "_api_operation" nor the pair "_api_resource_class" and "_api_operation_name" names one.', $request->getMethod(), RequestPath::decoded($request)));
    }
}
