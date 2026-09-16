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

namespace Thelia\Action;

use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\File\FileToggleVisibilityEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdateFilePositionEvent;
use Thelia\Core\File\Exception\FileException;
use Thelia\Core\File\FileManager;
use Thelia\Model\Map\ProductVideoTableMap;
use Thelia\Model\ProductVideo as ProductVideoModel;

/**
 * Product video management.
 *
 * A platform video is a row and nothing else: the shop stores the platform and
 * the identifier, never the address a merchant pasted. A hosted video is a row
 * plus a file copied into the video library, through the same FileManager the
 * images and the documents go through.
 *
 * What this deliberately does not do is enter the image pipeline: no
 * IMAGE_PROCESS, no ImageEvent, no cached rendition. A video is served as it was
 * uploaded, and the thumbnail a merchant chooses is a product image, which the
 * image pipeline already knows how to render.
 */
class ProductVideo extends BaseAction implements EventSubscriberInterface
{
    public function __construct(
        private readonly FileManager $fileManager,
    ) {
    }

    /**
     * @throws FileException
     */
    public function saveVideo(FileCreateOrUpdateEvent $event): void
    {
        $model = $event->getModel();

        if (!$model instanceof ProductVideoModel) {
            return;
        }

        $uploadedFile = $event->getUploadedFile();
        $con = Propel::getWriteConnection(ProductVideoTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            if ($uploadedFile instanceof UploadedFile) {
                // The row has to exist before the file can be named after it, so the
                // file column holds the temporary name until the copy renames it.
                $model->setFile(\sprintf('tmp/%s', $uploadedFile->getFilename()));
            }

            $savedLines = $model->save($con);
            $event->setModel($model);

            if (0 === $savedLines) {
                throw new FileException(\sprintf('Video with parent id %s failed to be saved', $event->getParentId()));
            }

            if ($uploadedFile instanceof UploadedFile) {
                $event->setUploadedFile($this->fileManager->copyUploadedFile($model, $uploadedFile));
            }

            $con->commit();
        } catch (\Throwable $exception) {
            $con->rollBack();

            throw $exception;
        }
    }

    public function updateVideo(FileCreateOrUpdateEvent $event): void
    {
        $model = $event->getModel();

        if (!$model instanceof ProductVideoModel) {
            return;
        }

        $uploadedFile = $event->getUploadedFile();

        if ($uploadedFile instanceof UploadedFile) {
            $oldModel = $event->getOldModel();

            if ($oldModel instanceof ProductVideoModel && $oldModel->isHostedFile()) {
                $this->removeStoredFile($oldModel);
            }

            $model->setFile('')->save();
            $event->setUploadedFile($this->fileManager->copyUploadedFile($model, $uploadedFile));
        }

        $model->save();
        $event->setModel($model);
    }

    public function deleteVideo(FileDeleteEvent $event): void
    {
        $model = $event->getFileToDelete();

        if (!$model instanceof ProductVideoModel) {
            return;
        }

        if ($model->isHostedFile()) {
            $this->removeStoredFile($model);
        }

        $model->delete();
    }

    public function updatePosition(UpdateFilePositionEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericUpdatePosition($event->getQuery(), $event, $dispatcher);
    }

    public function toggleVisibility(FileToggleVisibilityEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericToggleVisibility($event->getQuery(), $event, $dispatcher);
    }

    /**
     * Removes the file from the video library, leaving the row to the caller.
     */
    private function removeStoredFile(ProductVideoModel $model): void
    {
        @unlink(str_replace('..', '', $model->getUploadDir().DS.$model->getFile()));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::PRODUCT_VIDEO_CREATE => ['saveVideo', 128],
            TheliaEvents::PRODUCT_VIDEO_UPDATE => ['updateVideo', 128],
            TheliaEvents::PRODUCT_VIDEO_DELETE => ['deleteVideo', 128],
            TheliaEvents::PRODUCT_VIDEO_UPDATE_POSITION => ['updatePosition', 128],
            TheliaEvents::PRODUCT_VIDEO_TOGGLE_VISIBILITY => ['toggleVisibility', 128],
        ];
    }
}
