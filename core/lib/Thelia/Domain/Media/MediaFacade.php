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

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\File\FileToggleVisibilityEvent;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdateFilePositionEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\File\FileManager;
use Thelia\Core\File\FileModelInterface;
use Thelia\Core\File\Service\FileProcessorService;
use Thelia\Domain\Media\DTO\DocumentUploadDTO;
use Thelia\Domain\Media\DTO\ImageProcessDTO;
use Thelia\Domain\Media\DTO\ImageUpdateDTO;
use Thelia\Domain\Media\DTO\ImageUploadDTO;
use Thelia\Domain\Media\DTO\ProductVideoCreateDTO;
use Thelia\Domain\Media\DTO\ProductVideoUpdateDTO;
use Thelia\Domain\Media\Video\VideoProvider;
use Thelia\Model\ProductVideo;

final readonly class MediaFacade
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private FileManager $fileManager,
        private FileProcessorService $fileProcessorService,
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
        if (null !== $dto->alt) {
            $model->setAlt($dto->alt);
        }
        $model->setDecorative($dto->decorative ? 1 : 0);

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
        if (null !== $dto->alt) {
            $image->setAlt($dto->alt);
        }
        if (null !== $dto->decorative) {
            $image->setDecorative($dto->decorative ? 1 : 0);
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

    /**
     * Attaches a video to a product.
     *
     * The caller has already turned the address a merchant pasted into a platform
     * and an identifier through VideoProviderResolver, or hands an uploaded file
     * for a video the shop stores itself.
     */
    public function createVideo(ProductVideoCreateDTO $dto): ProductVideo
    {
        $this->guardUploadedVideo($dto->uploadedFile);

        $video = new ProductVideo();
        $video->setParentId($dto->productId);
        $video->setProvider(($dto->provider ?? VideoProvider::File)->value);
        $video->setExternalId($dto->externalId);
        $video->setThumbnailImageId($dto->thumbnailImageId);
        $video->setVisible($dto->visible ? 1 : 0);
        $video->setLocale($dto->locale);

        $this->writeVideoWording(
            $video,
            $dto->title,
            $dto->alt,
            $dto->description,
            $dto->chapo,
            $dto->postscriptum,
        );

        $event = new FileCreateOrUpdateEvent($dto->productId);
        $event->setModel($video);
        $event->setUploadedFile($dto->uploadedFile);
        $event->setParentName('product');

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_VIDEO_CREATE);

        /** @var ProductVideo $created */
        $created = $event->getModel();

        return $created;
    }

    public function updateVideo(ProductVideo $video, ProductVideoUpdateDTO $dto): ProductVideo
    {
        $this->guardUploadedVideo($dto->uploadedFile);

        $oldModel = clone $video;

        $video->setLocale($dto->locale);

        if (null !== $dto->provider) {
            $video->setProvider($dto->provider->value);
        }
        if (null !== $dto->externalId) {
            $video->setExternalId($dto->externalId);
        }
        if (null !== $dto->thumbnailImageId) {
            $video->setThumbnailImageId(false === $dto->thumbnailImageId ? null : $dto->thumbnailImageId);
        }
        if (null !== $dto->visible) {
            $video->setVisible($dto->visible ? 1 : 0);
        }

        $this->writeVideoWording(
            $video,
            $dto->title,
            $dto->alt,
            $dto->description,
            $dto->chapo,
            $dto->postscriptum,
        );

        $event = new FileCreateOrUpdateEvent($video->getParentId());
        $event->setModel($video);
        $event->setOldModel($oldModel);
        $event->setUploadedFile($dto->uploadedFile);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_VIDEO_UPDATE);

        /** @var ProductVideo $updated */
        $updated = $event->getModel();

        return $updated;
    }

    /**
     * Deletes a video, and the file it is stored in when the shop hosts it.
     */
    public function deleteVideo(ProductVideo $video): void
    {
        $this->dispatcher->dispatch(new FileDeleteEvent($video), TheliaEvents::PRODUCT_VIDEO_DELETE);
    }

    public function updateVideoPosition(ProductVideo $video, int $position, int $mode = UpdatePositionEvent::POSITION_ABSOLUTE): void
    {
        $event = new UpdateFilePositionEvent($video->getQueryInstance(), $video->getId(), $mode, $position);

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_VIDEO_UPDATE_POSITION);
    }

    public function toggleVideoVisibility(ProductVideo $video): void
    {
        $event = new FileToggleVisibilityEvent($video->getQueryInstance(), $video->getId());

        $this->dispatcher->dispatch($event, TheliaEvents::PRODUCT_VIDEO_TOGGLE_VISIBILITY);
    }

    /**
     * Applies the shop upload policy to a video before anything is written.
     *
     * The policy lives here rather than in each caller: the video library is
     * published into the web space by symbolic link, so a file the shop accepts is
     * a file the shop serves. A back-office screen calling the facade gets the same
     * refusal the API gets, and a caller that forgets to ask cannot be the hole.
     *
     * @throws ProcessFileException when the file may not be uploaded
     */
    private function guardUploadedVideo(?UploadedFile $uploadedFile): void
    {
        if (!$uploadedFile instanceof UploadedFile) {
            return;
        }

        $this->fileProcessorService->validateUpload($uploadedFile, 'video');
        $this->fileProcessorService->sanitizeUpload($uploadedFile);
    }

    /**
     * Writes the fields the caller carried, in the locale already set on the model.
     * A field left null is one the caller said nothing about.
     */
    private function writeVideoWording(
        ProductVideo $video,
        ?string $title,
        ?string $alt,
        ?string $description,
        ?string $chapo,
        ?string $postscriptum,
    ): void {
        if (null !== $title) {
            $video->setTitle($title);
        }
        if (null !== $alt) {
            $video->setAlt($alt);
        }
        if (null !== $description) {
            $video->setDescription($description);
        }
        if (null !== $chapo) {
            $video->setChapo($chapo);
        }
        if (null !== $postscriptum) {
            $video->setPostscriptum($postscriptum);
        }
    }
}
