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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Service\ApiResourceService;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Config\DatabaseConfiguration;

class PropelPersistProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ApiResourceService $apiResourceService,
        private readonly RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $resourceAddons = [];
        $propelModel = $this->apiResourceService->resourceToModel($data, $context);

        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        $connection->beginTransaction();

        try {
            $this->beforeSave($data, $operation, $propelModel);
            $propelModel->save();

            $jsonData = json_decode($this->requestStack->getCurrentRequest()->getContent(), true);
            $resourceAddonDefinitions = $this->apiResourceService->getResourceAddonDefinitions($data::class);
            foreach ($resourceAddonDefinitions as $addonShortName => $addonClass) {
                if (!isset($jsonData[$addonShortName])) {
                    $resourceAddons[$addonShortName] = null;
                    continue;
                }

                if (is_subclass_of($addonClass, ResourceAddonInterface::class)) {
                    $addon = (new $addonClass())->buildFromArray($jsonData[$addonShortName], $data);
                    $addon->doSave($propelModel, $data);
                    $resourceAddons[$addonShortName] = $addon;
                }
            }

            $connection->commit();

            $propelModel->reload();

            $data->setId($propelModel->getId());
        } catch (\Exception $exception) {
            $connection->rollBack();

            throw $exception;
        }

        /** @var Post $postOperation */
        $postOperation = $context['operation'] ?? null;
        if (null !== $postOperation) {
            $data = $this->apiResourceService->modelToResource(
                resourceClass: $data::class,
                propelModel: $propelModel,
                context: $postOperation->getNormalizationContext(),
                withAddon: false
            );
            foreach ($resourceAddons as $addonShortName => $addon) {
                $data->setResourceAddon($addonShortName, $addon);
            }
        }

        return $data;
    }

    private function beforeSave(mixed $data, Operation $operation, &$propelModel): void
    {
        if ($operation::class !== Put::class) {
            return;
        }

        $reflector = new \ReflectionClass($data);

        foreach ($reflector->getProperties() as $property) {
            $propelGetter = 'get'.ucfirst($property->getName());
            // todo add propel getter
            foreach ($property->getAttributes(Relation::class) as $relationAttribute) {
                if (isset($relationAttribute->getArguments()['targetResource'])) {
                    $reflectorChild = new \ReflectionClass($relationAttribute->getArguments()['targetResource']);
                    $compositeIdentifiers = $this->apiResourceService->getResourceCompositeIdentifierValues(reflector: $reflectorChild, param: 'keys');

                    if ($compositeIdentifiers === [] || !$propelModel->$propelGetter() instanceof Collection) {
                        continue;
                    }

                    foreach ($propelModel->$propelGetter()->getData() as $item) {
                        /** @var ModelCriteria $queryClass */
                        $queryClass = $item::class.'Query';

                        /** @var ModelCriteria $query */
                        $query = $queryClass::create();

                        foreach ($compositeIdentifiers as $compositeIdentifier) {
                            $filter = 'filterBy'.ucfirst($compositeIdentifier).'Id';
                            $getter = 'get'.ucfirst($compositeIdentifier).'Id';
                            if (!method_exists($item, $getter) || !method_exists($query, $filter)) {
                                return;
                            }
                            $id = $item->$getter();
                            $query->$filter($id);
                        }
                        if ($query->findOne() !== null) {
                            $item->setNew(false);
                        }
                    }
                }
            }
        }
    }
}
