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

namespace Thelia\Api\Bridge\Propel\State;

use ApiPlatform\Exception\RuntimeException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\UriVariablesResolverTrait;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Event\ItemProviderEvent;
use Thelia\Api\Bridge\Propel\Service\ApiResourceService;
use Thelia\Api\Resource\PropelResourceInterface;

class PropelItemProvider implements ProviderInterface
{
    public function __construct(
        readonly private ApiResourceService $apiResourceService,
        readonly private iterable $propelItemExtensions = [],
        readonly private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $resourceClass = $operation->getClass();

        if (!is_subclass_of($resourceClass, PropelResourceInterface::class)) {
            throw new RuntimeException('Bad provider');
        }

        /** @var ModelCriteria $queryClass */
        $queryClass = $resourceClass::getPropelRelatedTableMap()->getClassName().'Query';

        /** @var ModelCriteria $query */
        $query = $queryClass::create();

        $itemProviderEvent = new ItemProviderEvent(
            query: $query,
            operation: $operation,
            uriVariables: $uriVariables,
            context: $context
        );

        $this->eventDispatcher->dispatch($itemProviderEvent);

        $query = $itemProviderEvent->getQuery();

        foreach ($this->propelItemExtensions as $extension) {
            $extension->applyToItem($query, $resourceClass, $operation, $context);
        }

        $propelModel = $query->findOne();

        if (null === $propelModel) {
            return null;
        }

        return $this->apiResourceService->modelToResource(
            resourceClass: $resourceClass,
            propelModel: $propelModel,
            context: $context
        );
    }
}
