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
use Thelia\Api\Resource\PropelResourceInterface;

class PropelItemProvider implements ProviderInterface
{
    public function __construct(private iterable $collectionExtensions = [])
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = [])
    {
        $resourceClass = $operation->getClass();

        if (!is_subclass_of($resourceClass, PropelResourceInterface::class)) {
            throw new RuntimeException('Bad provider');
        }

        /** @var ModelCriteria $queryClass */
        $queryClass = $resourceClass::getPropelModelClass().'Query';

        /** @var ModelCriteria $query */
        $query = $queryClass::create();

        foreach ($this->collectionExtensions as $extension) {
            $extension->applyToCollection($query, $resourceClass, $operation->getName(), $context);

            if ($extension instanceof QueryResultCollectionExtensionInterface && $extension->supportsResult($resourceClass, $operation->getName(), $context)) {
                return $extension->getResult($query, $resourceClass, $operation->getName(), $context);
            }
        }

//        $items = array_map(
//            function ($propelModel) use ($resourceClass) {
//                $apiResource = new $resourceClass;
//                foreach (get_class_methods($apiResource) as $methodName) {
//                    if (!str_starts_with($methodName, 'set')) {
//                        continue;
//                    }
//                    $propelGetter = 'get'.ucfirst(substr($methodName, 3));
//
//                    if (!method_exists($propelModel, $propelGetter)) {
//                        continue;
//                    }
//
//                    $apiResource->$methodName($propelModel->$propelGetter());
//                }
//
//                return $apiResource;
//            },
//            iterator_to_array($query->find())
//        );

        return $query->find()->toArray();
    }
}
