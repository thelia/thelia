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

namespace Thelia\Domain\Media;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\File\FileToggleVisibilityEvent;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\File\FileManager;
use Thelia\Core\File\FileModelInterface;
use Thelia\Domain\Media\DTO\DocumentUploadDTO;
use Thelia\Domain\Media\DTO\ImageProcessDTO;
use Thelia\Domain\Media\DTO\ImageUpdateDTO;
use Thelia\Domain\Media\DTO\ImageUploadDTO;

final readonly class MediaFacade
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private FileManager $fileManager,
    ) {
    }

    public function uploadImage(ImageUploadDTO $dto): FileModelInterface
    {
        $model = $this->fileManager->getModelInstance('image', $dto->parentType);
        $model->setParentId($dto->parentId);
        $model->setLocale($dto->locale);
        $model->setVisible($dto->visible);

        if (null !== $dto->title) {
            $model->setTitle($dto->title);
        }
        if (null !== $dto->chapo) {
            $model->setChapo($dto->chapo);
        }
        if (null !== $dto->description) {
            $model->setDescription($dto->description);
        }
        if (null !== $dto->postscriptum) {
            $model->setPostscriptum($dto->postscriptum);
        }

        $event = new FileCreateOrUpdateEvent($dto->parentId);
        $event->setModel($model);
        $event->setUploadedFile($dto->uploadedFile);
        $event->setParentName($dto->parentType);

        $this->dispatcher->dispatch($event, TheliaEvents::IMAGE_SAVE);

        return $event->getModel();
    }

    public function updateImage(FileModelInterface $image, ImageUpdateDTO $dto): FileModelInterface
    {
        $image->setLocale($dto->locale);

        if (null !== $dto->title) {
            $image->setTitle($dto->title);
        }
        if (null !== $dto->chapo) {
            $image->setChapo($dto->chapo);
        }
        if (null !== $dto->description) {
            $image->setDescription($dto->description);
        }
        if (null !== $dto->postscriptum) {
            $image->setPostscriptum($dto->postscriptum);
        }
        if (null !== $dto->visible) {
            $image->setVisible($dto->visible);
        }

        $event = new FileCreateOrUpdateEvent($image->getParentId());
        $event->setModel($image);
        $event->setOldModel(clone $image);

        $this->dispatcher->dispatch($event, TheliaEvents::IMAGE_UPDATE);

        return $event->getModel();
    }

    public function deleteImage(FileModelInterface $image): void
    {
        $event = new FileDeleteEvent($image);

        $this->dispatcher->dispatch($event, TheliaEvents::IMAGE_DELETE);
    }

    public function updateImagePosition(FileModelInterface $image, int $position, int $mode = UpdatePositionEvent::POSITION_ABSOLUTE): void
    {
        $event = new UpdatePositionEvent($image->getId(), $mode, $position);

        $this->dispatcher->dispatch($event, TheliaEvents::IMAGE_UPDATE_POSITION);
    }

    public function toggleImageVisibility(FileModelInterface $image): void
    {
        $event = new FileToggleVisibilityEvent($image->getQueryInstance(), $image->getId());

        $this->dispatcher->dispatch($event, TheliaEvents::IMAGE_TOGGLE_VISIBILITY);
    }

    public function processImage(ImageProcessDTO $dto): ImageEvent
    {
        $event = new ImageEvent();
        $event->setSourceFilepath($dto->sourceFilepath);
        $event->setCacheSubdirectory($dto->cacheSubdirectory);

        if (null !== $dto->width) {
            $event->setWidth($dto->width);
        }
        if (null !== $dto->height) {
            $event->setHeight($dto->height);
        }
        if (null !== $dto->resizeMode) {
            $event->setResizeMode($dto->resizeMode);
        }
        if (null !== $dto->backgroundColor) {
            $event->setBackgroundColor($dto->backgroundColor);
        }
        if (!empty($dto->effects)) {
            $event->setEffects($dto->effects);
        }
        if (null !== $dto->rotation) {
            $event->setRotation($dto->rotation);
        }
        if (null !== $dto->quality) {
            $event->setQuality($dto->quality);
        }
        if (null !== $dto->format) {
            $event->setFormat($dto->format);
        }

        $event->setAllowZoom($dto->allowZoom);

        $this->dispatcher->dispatch($event, TheliaEvents::IMAGE_PROCESS);

        return $event;
    }

    public function clearImageCache(string $sourceFilepath, string $cacheSubdirectory): void
    {
        $event = new ImageEvent();
        $event->setSourceFilepath($sourceFilepath);
        $event->setCacheSubdirectory($cacheSubdirectory);

        $this->dispatcher->dispatch($event, TheliaEvents::IMAGE_CLEAR_CACHE);
    }

    public function uploadDocument(DocumentUploadDTO $dto): FileModelInterface
    {
        $model = $this->fileManager->getModelInstance('document', $dto->parentType);
        $model->setParentId($dto->parentId);
        $model->setLocale($dto->locale);
        $model->setVisible($dto->visible);

        if (null !== $dto->title) {
            $model->setTitle($dto->title);
        }
        if (null !== $dto->chapo) {
            $model->setChapo($dto->chapo);
        }
        if (null !== $dto->description) {
            $model->setDescription($dto->description);
        }
        if (null !== $dto->postscriptum) {
            $model->setPostscriptum($dto->postscriptum);
        }

        $event = new FileCreateOrUpdateEvent($dto->parentId);
        $event->setModel($model);
        $event->setUploadedFile($dto->uploadedFile);
        $event->setParentName($dto->parentType);

        $this->dispatcher->dispatch($event, TheliaEvents::DOCUMENT_SAVE);

        return $event->getModel();
    }

    public function deleteDocument(FileModelInterface $document): void
    {
        $event = new FileDeleteEvent($document);

        $this->dispatcher->dispatch($event, TheliaEvents::DOCUMENT_DELETE);
    }

    public function updateDocumentPosition(FileModelInterface $document, int $position, int $mode = UpdatePositionEvent::POSITION_ABSOLUTE): void
    {
        $event = new UpdatePositionEvent($document->getId(), $mode, $position);

        $this->dispatcher->dispatch($event, TheliaEvents::DOCUMENT_UPDATE_POSITION);
    }

    public function toggleDocumentVisibility(FileModelInterface $document): void
    {
        $event = new FileToggleVisibilityEvent($document->getQueryInstance(), $document->getId());

        $this->dispatcher->dispatch($event, TheliaEvents::DOCUMENT_TOGGLE_VISIBILITY);
    }

    public function clearDocumentCache(string $sourceFilepath, string $cacheSubdirectory): void
    {
        $event = new ImageEvent();
        $event->setSourceFilepath($sourceFilepath);
        $event->setCacheSubdirectory($cacheSubdirectory);

        $this->dispatcher->dispatch($event, TheliaEvents::DOCUMENT_CLEAR_CACHE);
    }
}
