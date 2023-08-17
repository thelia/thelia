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
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Resource\PropelResourceInterface;

class PropelItemProvider extends AbstractPropelProvider
{
    public function __construct(readonly private iterable $propelItemExtensions = [])
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
        $identifiers = array_values($uriVariables);
        $query->filterByPrimaryKey(\count($identifiers) === 1 ? $identifiers[0] : $identifiers);

        foreach ($this->propelItemExtensions as $extension) {
            $extension->applyToItem($query, $resourceClass, $operation, $context);
        }

        $propelModel = $query->findOne();

        if (null === $propelModel) {
            return null;
        }

        return $this->modelToResource($resourceClass, $propelModel, $context);
    }
}
