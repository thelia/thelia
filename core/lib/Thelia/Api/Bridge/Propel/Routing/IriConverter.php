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

namespace Thelia\Api\Bridge\Propel\Routing;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Api\UrlGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Util\ClassInfoTrait;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Api\Bridge\Propel\Attribute\CompositeIdentifiers;
use Thelia\Log\Tlog;

#[AsDecorator(decorates: 'api_platform.symfony.iri_converter')]
class IriConverter implements IriConverterInterface
{
    use ClassInfoTrait;

    public function __construct(
        #[AutowireDecorated]
        private IriConverterInterface $decorated,
        private readonly RouterInterface $router,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory
    ) {
    }

    public function getResourceFromIri(string $iri, array $context = [], Operation $operation = null): object
    {
        return $this->decorated->getResourceFromIri($iri, $context, $operation);
    }

    public function getIriFromResource(object|string $resource, int $referenceType = UrlGeneratorInterface::ABS_PATH, Operation $operation = null, array $context = []): ?string
    {
        $resourceClass = $resource;
        if (\is_object($resource)) {
            $resourceClass = $resource::class;
        }

        $reflector = new \ReflectionClass($resourceClass);

        $compositeIdentifiers = $reflector->getAttributes(CompositeIdentifiers::class);

        if (!$operation) {
            $operation = $this->resourceMetadataCollectionFactory->create($resourceClass)->getOperation(null, false, true);
        }

        if (\is_object($resource) && !empty($compositeIdentifiers) && null !== $operation) {
            try {
                $identifiers = array_reduce(
                    $compositeIdentifiers[0]->getArguments()[0],
                    function ($carry, $identifier) use ($resource) {
                        $getter = 'get'.ucfirst($identifier);
                        $carry[$identifier] = $resource->$getter()->getId();

                        return $carry;
                    },
                    []
                );

                return $this->router->generate($operation->getName(), $identifiers, $operation->getUrlGenerationStrategy() ?? $referenceType);
            } catch (\Exception $e) {
                // try with not decorated converter
            }
        }
        try {
            return $this->decorated->getIriFromResource($resource, $referenceType, $operation, $context);
        } catch (\Exception $e) {
            Tlog::getInstance()->warning('Iri convert failure : '.$e->getMessage());

            return 'undefined_iri';
        }
    }
}
