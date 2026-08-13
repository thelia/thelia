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

namespace Thelia\Api\Bridge\Propel\Routing;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Keeps an IRI on the surface the request is being served from.
 *
 * A resource declares its admin and its front side in two #[ApiResource] blocks
 * on the same class. When the serializer asks for the IRI of an item it has no
 * operation to hand — a member of a collection, a relation, the body of a POST —
 * and API Platform then resolves the first item operation of the class, which is
 * the admin one. A front collection therefore answered with admin IRIs: links a
 * front client cannot follow, pointing at a surface it should not know about.
 *
 * The operation being served says which surface the answer belongs to, so the
 * item operation is looked up under the same first path segment.
 */
final class SurfaceAwareIriConverter implements IriConverterInterface
{
    /** @var array<string, HttpOperation|false> */
    private array $operationCache = [];

    public function __construct(
        private readonly IriConverterInterface $decorated,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getResourceFromIri(string $iri, array $context = [], ?Operation $operation = null): object
    {
        return $this->decorated->getResourceFromIri($iri, $context, $operation);
    }

    public function getIriFromResource(object|string $resource, int $referenceType = UrlGeneratorInterface::ABS_PATH, ?Operation $operation = null, array $context = []): ?string
    {
        if ($this->needsSurfaceOperation($resource, $operation, $context)) {
            /** @var object $resource */
            $resourceClass = $context['force_resource_class'] ?? $resource::class;
            $operation = $this->itemOperationOnCurrentSurface($resourceClass) ?? $operation;
        }

        return $this->decorated->getIriFromResource($resource, $referenceType, $operation, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function needsSurfaceOperation(object|string $resource, ?Operation $operation, array $context): bool
    {
        // A class is asked for the IRI of a collection, which already carries its
        // own operation, and an explicit item template is a deliberate choice.
        if (\is_string($resource) || isset($context['item_uri_template'])) {
            return false;
        }

        // Anything else than an item operation leaves API Platform resolving one
        // on its own: no operation at all, or the POST that just created the item.
        return null === $operation
            || $operation instanceof CollectionOperationInterface
            || ($operation instanceof HttpOperation && 'POST' === $operation->getMethod());
    }

    private function itemOperationOnCurrentSurface(string $resourceClass): ?HttpOperation
    {
        $surface = $this->currentSurface();

        if (null === $surface) {
            return null;
        }

        $cacheKey = $surface.'|'.$resourceClass;

        if (!isset($this->operationCache[$cacheKey])) {
            $this->operationCache[$cacheKey] = $this->findItemOperation($resourceClass, $surface);
        }

        return $this->operationCache[$cacheKey] ?: null;
    }

    private function findItemOperation(string $resourceClass, string $surface): HttpOperation|false
    {
        try {
            $metadataCollection = $this->resourceMetadataCollectionFactory->create($resourceClass);
        } catch (\Throwable) {
            return false;
        }

        foreach ($metadataCollection as $resourceMetadata) {
            foreach ($resourceMetadata->getOperations() ?? [] as $operation) {
                if (
                    $operation instanceof HttpOperation
                    && !$operation instanceof CollectionOperationInterface
                    && 'GET' === $operation->getMethod()
                    && $surface === $this->surfaceOf($operation)
                ) {
                    return $operation;
                }
            }
        }

        return false;
    }

    private function currentSurface(): ?string
    {
        $operation = $this->requestStack->getCurrentRequest()?->attributes->get('_api_operation');

        return $operation instanceof HttpOperation ? $this->surfaceOf($operation) : null;
    }

    private function surfaceOf(HttpOperation $operation): ?string
    {
        $uriTemplate = $operation->getUriTemplate();

        if (null === $uriTemplate) {
            return null;
        }

        $segments = explode('/', ltrim($uriTemplate, '/'));

        return '' === $segments[0] ? null : $segments[0];
    }
}
