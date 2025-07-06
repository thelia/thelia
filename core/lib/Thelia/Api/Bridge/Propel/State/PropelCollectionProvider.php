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

namespace Thelia\Api\Bridge\Propel\State;

use ApiPlatform\Exception\RuntimeException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\Extension\QueryResultCollectionExtensionInterface;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Model\LangQuery;

readonly class PropelCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ApiResourcePropelTransformerService $apiResourcePropelTransformerService,
        private iterable $propelCollectionExtensions = [],
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $resourceClass = $operation->getClass();

        if (!is_subclass_of($resourceClass, PropelResourceInterface::class)) {
            throw new RuntimeException('Bad provider');
        }

        /** @var ModelCriteria $queryClass */
        $queryClass = $resourceClass::getPropelRelatedTableMap()?->getClassName().'Query';

        /** @var ModelCriteria $query */
        $query = $queryClass::create();

        $results = null;

        $resultExtensions = [];
        foreach ($this->propelCollectionExtensions as $extension) {
            $extension->applyToCollection($query, $resourceClass, $operation, $context);

            // Keep result extension for the end to apply all join / filter before
            if ($extension instanceof QueryResultCollectionExtensionInterface && $extension->supportsResult($resourceClass, $operation, $context)) {
                $resultExtensions[] = $extension;
            }
        }

        /** @var QueryResultCollectionExtensionInterface $resultExtension */
        foreach ($resultExtensions as $resultExtension) {
            $results = $resultExtension->getResult($query, $resourceClass, $operation, $context);
        }

        if (null === $results) {
            $results = $query->find();
        }

        $langs = LangQuery::create()->filterByActive(1)->find();

        return array_map(
            fn ($propelModel): PropelResourceInterface => $this->apiResourcePropelTransformerService->modelToResource(
                resourceClass: $resourceClass,
                propelModel: $propelModel,
                context: $context,
                langs: $langs
            ),
            iterator_to_array($results)
        );
    }
}
