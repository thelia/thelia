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
use ApiPlatform\State\ProcessorInterface;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\RequestStack;
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
            $propelModel->save();

            $jsonData = json_decode($this->requestStack->getCurrentRequest()->getContent(), true);
            $resourceAddonDefinitions = $this->apiResourceService->getResourceAddonDefinitions($data::class);
            foreach ($resourceAddonDefinitions as $addonShortName => $addonClass) {
                if (!isset($jsonData[$addonShortName])) {
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
                resourceClass: \get_class($data),
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
}
