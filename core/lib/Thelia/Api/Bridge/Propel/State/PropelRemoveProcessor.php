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
use ApiPlatform\State\ProcessorInterface;
use Propel\Runtime\Propel;
use Thelia\Api\Bridge\Propel\Service\ApiResourceService;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Config\DatabaseConfiguration;

class PropelRemoveProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ApiResourceService $apiResourceService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        $connection->beginTransaction();

        try {
            $propelModel = $data->getPropelModel();

            $resourceAddonDefinitions = $this->apiResourceService->getResourceAddonDefinitions($data::class);
            foreach ($resourceAddonDefinitions as $addonClass) {
                if (is_subclass_of($addonClass, ResourceAddonInterface::class)) {
                    $addon = (new $addonClass());
                    $addon->doDelete($propelModel, $data);
                }
            }

            $propelModel->delete();
            if (!$propelModel->isDeleted()) {
                throw new \Exception('This item cannot be deleted');
            }

            $connection->commit();
        } catch (\Exception $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }
}
