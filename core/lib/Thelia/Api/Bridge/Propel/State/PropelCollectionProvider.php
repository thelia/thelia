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
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\Extension\QueryResultCollectionExtensionInterface;
use Thelia\Api\Resource\I18n;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Model\LangQuery;

class PropelCollectionProvider extends AbstractPropelProvider
{
    public function __construct(private iterable $propelCollectionExtensions = [])
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $resourceClass = $operation->getClass();

        if (!is_subclass_of($resourceClass, PropelResourceInterface::class)) {
            throw new RuntimeException('Bad provider');
        }

        /** @var ModelCriteria $queryClass */
        $queryClass = $resourceClass::getPropelModelClass().'Query';

        /** @var ModelCriteria $query */
        $query = $queryClass::create();

        foreach ($this->propelCollectionExtensions as $extension) {
            $extension->applyToCollection($query, $resourceClass, $operation, $context);

            if ($extension instanceof QueryResultCollectionExtensionInterface && $extension->supportsResult($resourceClass, $operation->getName(), $context)) {
                return $extension->getResult($query, $resourceClass, $operation->getName(), $context);
            }
        }

        $langs = LangQuery::create()->filterByActive(1)->find();

        $items = array_map(
            function ($propelModel) use ($resourceClass, $context, $langs) {
                return $this->modelToResource($resourceClass, $propelModel, $context, $langs);
            },
            iterator_to_array($query->find())
        );

        return $items;
    }
}
