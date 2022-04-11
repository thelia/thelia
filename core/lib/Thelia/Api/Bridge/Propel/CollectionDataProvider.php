<?php

namespace Thelia\Api\Bridge\Propel;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Bridge\Propel\Extension\QueryCollectionExtensionInterface;
use Thelia\Api\Bridge\Propel\Extension\QueryResultCollectionExtensionInterface;
use Thelia\Api\Resource\PropelResourceInterface;

final class CollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    /**
     * @param QueryCollectionExtensionInterface[] $collectionExtensions
     */
    public function __construct(private iterable $collectionExtensions = [])
    {}

    /**
     * @param PropelResourceInterface $resourceClass
     * @param string|null $operationName
     * @param array $context
     * @return iterable|void
     */
    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): iterable
    {
        /** @var ModelCriteria $queryClass */
        $queryClass = $resourceClass::getPropelModelClass().'Query';

        /** @var ModelCriteria $query */
        $query = $queryClass::create();

        foreach ($this->collectionExtensions as $extension) {
            $extension->applyToCollection($query, $resourceClass, $operationName, $context);

            if ($extension instanceof QueryResultCollectionExtensionInterface && $extension->supportsResult($resourceClass, $operationName, $context)) {
                return $extension->getResult($query, $resourceClass, $operationName, $context);
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

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return is_subclass_of($resourceClass, PropelResourceInterface::class);
    }
}
