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

namespace Thelia\Api\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Event\ResourcePersistedEvent;
use Thelia\Api\Resource\ItemFileResourceInterface;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Core\File\FileModelInterface;
use Thelia\Domain\Media\MediaFacade;

/**
 * Deletes an image or a document the way the back office does, through the media
 * facade, so that its files go with the row: the file it stores in every language,
 * and the copies published in the web space to serve it.
 *
 * Deleting the row alone left both on disk, the published copy still served at its
 * address.
 */
final readonly class ItemFileRemoveProcessor implements ProcessorInterface
{
    public function __construct(
        private MediaFacade $mediaFacade,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$data instanceof ItemFileResourceInterface || !$data instanceof PropelResourceInterface) {
            throw new \LogicException(\sprintf('%s only deletes file resources.', self::class));
        }

        $model = $data->getPropelModel();

        if (!$model instanceof FileModelInterface) {
            throw new \LogicException(\sprintf('The model of %s is not a file model.', $data::class));
        }

        if ('image' === $data::getFileType()) {
            $this->mediaFacade->deleteImage($model);
        } else {
            $this->mediaFacade->deleteDocument($model);
        }

        $this->eventDispatcher->dispatch(new ResourcePersistedEvent($data::class, $model, ResourcePersistedEvent::OPERATION_DELETE));

        return null;
    }
}
