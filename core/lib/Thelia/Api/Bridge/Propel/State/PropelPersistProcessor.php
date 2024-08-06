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
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Controller\Admin\PostItemFileController;
use Thelia\Api\Resource\ItemFileResourceInterface;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Config\DatabaseConfiguration;

readonly class PropelPersistProcessor implements ProcessorInterface
{
    public function __construct(
        private ApiResourcePropelTransformerService $apiResourcePropelTransformerService,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $propelModel = $this->apiResourcePropelTransformerService->resourceToModel($data, $context);
        if (isset($uriVariables['id'])) {
            $propelModel->setId($uriVariables['id']);
        }
        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        $connection->beginTransaction();
        try {
            $this->beforeSave($data, $operation, $propelModel);
            $implementsItemFileResource =
                \in_array(ItemFileResourceInterface::class, class_implements($data), true)
                && $operation->getController() === PostItemFileController::class;

            if ($implementsItemFileResource) {
                $propelModel->setNew(false);
            }
            $propelModel->save();
            if (!$implementsItemFileResource) {
                $resourceAddons = $this->manageResourceAddons($propelModel, $data);
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
            $data = $this->apiResourcePropelTransformerService->modelToResource(
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
            $propelModel->setNew(true);

            return;
        }
        $propelModel->setNew(false);
        $reflector = new \ReflectionClass($data);

        foreach ($reflector->getProperties() as $property) {
            $propelGetter = 'get'.ucfirst($property->getName());

            foreach ($property->getAttributes(Relation::class) as $relationAttribute) {
                if (isset($relationAttribute->getArguments()['targetResource'])) {
                    $reflectorChild = new \ReflectionClass($relationAttribute->getArguments()['targetResource']);
                    $compositeIdentifiers = $this->apiResourcePropelTransformerService->getResourceCompositeIdentifierValues(reflector: $reflectorChild, param: 'keys');

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

    private function manageResourceAddons(
        ActiveRecordInterface $propelModel,
        PropelResourceInterface $data
    ): array {
        $resourceAddons = [];
        $jsonData = json_decode($this->requestStack->getCurrentRequest()?->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $resourceAddonDefinitions = $this->apiResourcePropelTransformerService->getResourceAddonDefinitions($data::class);
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

        return $resourceAddons;
    }
}
