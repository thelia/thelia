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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Propel\Runtime\Propel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Event\ResourcePersistedEvent;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Domain\Media\DeletedItemMedia;
use Thelia\Domain\Media\PendingMediaDeletions;

readonly class PropelRemoveProcessor implements ProcessorInterface
{
    public function __construct(
        private ApiResourcePropelTransformerService $apiResourcePropelTransformerService,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        $connection->beginTransaction();
        PendingMediaDeletions::begin();

        try {
            $propelModel = $data->getPropelModel();
            $resourceAddonDefinitions = $this->apiResourcePropelTransformerService->getResourceAddonDefinitions($data::class);

            foreach ($resourceAddonDefinitions as $addonClass) {
                if (is_subclass_of($addonClass, ResourceAddonInterface::class)) {
                    $addon = (new $addonClass())
                        ->setContext($context);
                    $addon->doDelete($propelModel, $data);
                }
            }

            // Read while the media rows still exist, deleted once the item is gone.
            PendingMediaDeletions::add(DeletedItemMedia::of($propelModel));

            $propelModel->delete();

            if (!$propelModel->isDeleted()) {
                throw new \Exception('This item cannot be deleted, possibly because it is the default one.');
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            PendingMediaDeletions::rollBack();

            throw $exception;
        }

        PendingMediaDeletions::commit($this->eventDispatcher);

        $this->eventDispatcher?->dispatch(new ResourcePersistedEvent($data::class, $propelModel, ResourcePersistedEvent::OPERATION_DELETE));
    }
}
