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
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProviderInterface;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\Collection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Event\ItemProviderQueryEvent;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\State\Provider\TFiltersProvider;
use Thelia\Model\LangQuery;

readonly class PropelItemProvider implements ProviderInterface
{
    public function __construct(
        private ApiResourcePropelTransformerService $apiResourcePropelTransformerService,
        private iterable $propelItemExtensions = [],
        private EventDispatcherInterface $eventDispatcher,
        private TFiltersProvider $filtersProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $resourceClass = $operation->getClass();

        if (!is_subclass_of($resourceClass, PropelResourceInterface::class)) {
            throw new RuntimeException('Bad provider');
        }

        if ($operation->getProvider() !== self::class) {
            return $this->filtersProvider->provide(operation: $operation, uriVariables: $uriVariables, context: $context);
        }

        /** @var ModelCriteria $queryClass */
        $queryClass = $resourceClass::getPropelRelatedTableMap()->getClassName().'Query';

        /** @var ModelCriteria $query */
        $query = $queryClass::create();
        $itemProviderEvent = new ItemProviderQueryEvent(
            query: $query,
            operation: $operation,
            uriVariables: $uriVariables,
            context: $context,
            resourceClass : $resourceClass,
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
        $langs = null;
        if (\in_array($operation::class, [Patch::class, Put::class])) {
            $langs = new Collection();
            $content = json_decode($context['request']->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            if (isset($content['i18ns'])) {
                $langs = LangQuery::create()->filterByLocale(array_keys($content['i18ns']))->find();
            }
        }

        return $this->apiResourcePropelTransformerService->modelToResource(
            resourceClass: $resourceClass,
            propelModel: $propelModel,
            context: $context,
            langs : $langs
        );
    }
}
