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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Document\DocumentEvent;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\File\Exception\FileException;
use Thelia\Domain\Media\ProductMediaOrder;
use Thelia\Domain\Media\Video\IncompleteVideoException;
use Thelia\Domain\Media\Video\VideoProvider;
use Thelia\Exception\DocumentException;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductVideoTableMap;
use Thelia\Model\ProductVideo as ProductVideoModel;
use Thelia\Model\ProductVideoI18nQuery;
use Thelia\Model\ProductVideoQuery;
use Thelia\Tools\URL;

/**
 * Product video management, and the web-space cache of the videos the shop hosts.
 *
 * A platform video is a row and nothing else: the shop stores the platform and
 * the identifier, never the address a merchant pasted. A hosted video is a row,
 * a file copied into the video library, and a link to that file under the web
 * root — the library lives outside the web space, exactly like the document one,
 * so a visitor can only ever be handed the cached link.
 *
 * What this deliberately does not do is enter the image pipeline: no
 * IMAGE_PROCESS, no ImageEvent, no rendition. A video is served byte for byte as
 * it was uploaded, and the thumbnail a merchant chooses is a product image,
 * which the image pipeline already knows how to render.
 */
class ProductVideo extends BaseCachedFile implements EventSubscriberInterface
{
    /** Config key for video delivery mode, shared with the documents. */
    public const CONFIG_DELIVERY_MODE = 'original_document_delivery_mode';

    /**
     * The subdirectory of the cache a product video is written to.
     */
    public const CACHE_SUBDIRECTORY = 'product';

    /**
     * @return string root of the video cache directory in web space
     */
    protected function getCacheDirFromWebRoot(): string
    {
        return ConfigQuery::read('video_cache_dir_from_web_root', 'cache'.DS.'videos');
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
                // An uploaded video is one the shop hosts, whatever the caller said:
                // the generic upload path carries a file and no platform.
                $model->setProvider(VideoProvider::File->value);
                // The row has to exist before the file can be named after it, so the
                // file column holds the temporary name until the copy renames it.
                $model->setFile(\sprintf('tmp/%s', $uploadedFile->getFilename()));
            }

            $this->guardAgainstAnEmptyVideo($model, $uploadedFile);

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
        $oldModel = $event->getOldModel();
        $leftHostedFile = $oldModel instanceof ProductVideoModel && $oldModel->isHostedFile();

        if ($uploadedFile instanceof UploadedFile) {
            if ($leftHostedFile) {
                $this->removeStoredFile($oldModel);
            }

            $model->setProvider(VideoProvider::File->value);
            $model->setFile('')->save();
            $event->setUploadedFile($this->fileManager->copyUploadedFile($model, $uploadedFile));
        } elseif ($leftHostedFile && VideoProvider::File->value !== $model->getProvider()) {
            // The video plays from a platform now: the file the shop was serving for
            // it goes with it, in the library and in the web space, rather than
            // staying behind as a file nothing points at any more.
            $this->removeStoredFile($oldModel);
            $model->setFile('');
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

    /**
     * A video has to point at something: a file the shop stores, or an identifier
     * on a platform. Neither would be a row nothing can ever play, which no screen
     * would show and no one would notice until a merchant wondered why his gallery
     * is short of one item.
     *
     * @throws IncompleteVideoException
     */
    private function guardAgainstAnEmptyVideo(ProductVideoModel $model, ?UploadedFile $uploadedFile): void
    {
        $isHosted = VideoProvider::File->value === $model->getProvider();

        if ($isHosted && !$uploadedFile instanceof UploadedFile) {
            throw new IncompleteVideoException('A video hosted by the shop needs a file.');
        }

        if (!$isHosted && null === $model->getExternalId()) {
            throw new IncompleteVideoException('A video played from a platform needs the identifier of the video on it.');
        }
    }

    /**
     * Links the hosted video into the web space and hands back its address, on the
     * pattern of the documents: the library is outside the web root, so the file a
     * visitor downloads is the link this writes, never the stored one.
     *
     * @throws DocumentException
     */
    public function processVideo(DocumentEvent $event): void
    {
        $subdir = $event->getCacheSubdirectory();
        $sourceFile = $event->getSourceFilepath();

        if (null === $sourceFile) {
            throw new \InvalidArgumentException('Cache sub-directory and source file path cannot be null');
        }

        $videoPathInCache = $this->getCacheFilePath($subdir, $sourceFile, true);

        if (!file_exists($videoPathInCache)) {
            if (!file_exists($sourceFile)) {
                throw new DocumentException(\sprintf('Source video file %s does not exists.', $sourceFile));
            }

            $mode = ConfigQuery::read(self::CONFIG_DELIVERY_MODE, 'symlink');

            if ('symlink' === $mode) {
                if (false === symlink($sourceFile, $videoPathInCache)) {
                    throw new DocumentException(\sprintf('Failed to create symbolic link for %s in %s video cache directory', basename($sourceFile), $subdir));
                }
            } elseif (false === @copy($sourceFile, $videoPathInCache)) {
                // mode = 'copy'
                throw new DocumentException(\sprintf('Failed to copy %s in %s video cache directory', basename($sourceFile), $subdir));
            }
        }

        $videoUrl = $this->getCacheFileURL($subdir, basename($videoPathInCache));

        $event->setDocumentPath($videoUrl);
        $event->setDocumentUrl(URL::getInstance()->absoluteUrl($videoUrl, null, URL::PATH_TO_FILE, $this->cdnBaseUrl));
    }

    /**
     * Where the web space serves this video from. Asking for it creates the cache
     * directory, as every other path of this cache does; it does not create the
     * link itself, which is what processVideo() is for.
     */
    public function cachedFilePath(ProductVideoModel $model): string
    {
        return $this->getCacheFilePath(
            self::CACHE_SUBDIRECTORY,
            $model->getUploadDir().DS.$model->getFile(),
            true,
        );
    }

    /**
     * Removes the video from the library and the link that published it, leaving
     * the row to the caller.
     */
    private function removeStoredFile(ProductVideoModel $model): void
    {
        $cachedFile = $this->cachedFilePath($model);

        if (file_exists($cachedFile) || is_link($cachedFile)) {
            @unlink($cachedFile);
        }

        @unlink($model->getUploadDir().DS.basename($model->getFile()));
    }

    /**
     * Gives the clone the videos of its source: a platform video is the same
     * platform and identifier, a hosted one gets its own copy of the file, so
     * deleting one product never takes the file the other serves. Each copy
     * keeps its wording, its visibility and the copy of its thumbnail, and the
     * clone takes the order of the source gallery once images and videos are in.
     *
     * Runs after the images are cloned, whose copies it reads from the event.
     */
    public function cloneVideos(ProductCloneEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        $clonedProductId = (int) $event->getClonedProduct()->getId();
        $originalVideos = ProductVideoQuery::create()
            ->filterByProductId($event->getOriginalProduct()->getId())
            ->orderByPosition()
            ->find();

        foreach ($originalVideos as $originalVideo) {
            $clonedVideo = new ProductVideoModel();
            $clonedVideo
                ->setProductId($clonedProductId)
                ->setProvider($originalVideo->getProvider())
                ->setExternalId($originalVideo->getExternalId())
                ->setVisible($originalVideo->getVisible());

            $thumbnailImageId = $originalVideo->getThumbnailImageId();
            $clonedVideo->setThumbnailImageId(null === $thumbnailImageId ? null : $event->getClonedImageId($thumbnailImageId));

            foreach (ProductVideoI18nQuery::create()->findById($originalVideo->getId()) as $i18n) {
                $clonedVideo
                    ->setLocale($i18n->getLocale())
                    ->setTitle($i18n->getTitle())
                    ->setAlt($i18n->getAlt())
                    ->setDescription($i18n->getDescription())
                    ->setChapo($i18n->getChapo())
                    ->setPostscriptum($i18n->getPostscriptum());
            }

            $copiedFile = null;

            if ($originalVideo->isHostedFile()) {
                $sourcePath = $originalVideo->getUploadDir().DS.$originalVideo->getFile();

                if (!is_file($sourcePath)) {
                    Tlog::getInstance()->addWarning('Failed to find video file '.$sourcePath);

                    continue;
                }

                // The upload path moves the file it is handed: it gets a copy.
                $copyPath = $sourcePath.'.clone';
                copy($sourcePath, $copyPath);
                $copiedFile = new UploadedFile($copyPath, $originalVideo->getFile(), null, null, true);
            }

            $createEvent = new FileCreateOrUpdateEvent($clonedProductId);
            $createEvent
                ->setModel($clonedVideo)
                ->setUploadedFile($copiedFile)
                ->setParentName('product');

            $dispatcher->dispatch($createEvent, TheliaEvents::PRODUCT_VIDEO_CREATE);

            $event->addClonedVideoId((int) $originalVideo->getId(), (int) $clonedVideo->getId());
        }

        $this->giveTheCloneTheOrderOfItsSource($event);
    }

    /**
     * A new medium goes after every medium already there, so the clone would show
     * its images first and its videos after them. The source order is replayed on
     * the copies, leaving out a medium whose file could not be copied.
     */
    private function giveTheCloneTheOrderOfItsSource(ProductCloneEvent $event): void
    {
        $mediaOrder = new ProductMediaOrder();
        $order = [];

        foreach ($mediaOrder->orderOf((int) $event->getOriginalProduct()->getId()) as $entry) {
            $clonedId = ProductMediaOrder::TYPE_VIDEO === $entry['type']
                ? $event->getClonedVideoId($entry['id'])
                : $event->getClonedImageId($entry['id']);

            if (null !== $clonedId) {
                $order[] = ['type' => $entry['type'], 'id' => $clonedId];
            }
        }

        if ([] !== $order) {
            $mediaOrder->reorder((int) $event->getClonedProduct()->getId(), $order);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::PRODUCT_VIDEO_CREATE => ['saveVideo', 128],
            TheliaEvents::PRODUCT_VIDEO_UPDATE => ['updateVideo', 128],
            TheliaEvents::PRODUCT_VIDEO_DELETE => ['deleteVideo', 128],
            TheliaEvents::PRODUCT_VIDEO_PROCESS => ['processVideo', 128],

            // After the images (File::cloneFile, 128), whose copies it reads.
            TheliaEvents::FILE_CLONE => ['cloneVideos', 64],

            // Implemented in parent class BaseCachedFile
            TheliaEvents::PRODUCT_VIDEO_UPDATE_POSITION => ['updatePosition', 128],
            TheliaEvents::PRODUCT_VIDEO_TOGGLE_VISIBILITY => ['toggleVisibility', 128],
        ];
    }
}
