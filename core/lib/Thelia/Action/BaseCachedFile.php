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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Event\CachedFileEvent;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\File\FileToggleVisibilityEvent;
use Thelia\Core\Event\UpdateFilePositionEvent;
use Thelia\Core\File\Exception\FileException;
use Thelia\Core\File\FileManager;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductImageTableMap;
use Thelia\Tools\URL;

/**
 * Cached file management actions. This class handles file caching in the web space.
 *
 * Basically, files are stored outside the web space (by default in local/media/<dirname>),
 * and cached in the web space (by default in web/local/<dirname>).
 *
 * In the file cache directory, a subdirectory for files categories (eg. product, category, folder, etc.) is
 * automatically created, and the cached file is created here. Plugin may use their own subdirectory as required.
 *
 * A copy (or symbolic link, by default) of the original file is created in the cache.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
abstract class BaseCachedFile extends BaseAction
{
    protected ?string $cdnBaseUrl;

    public function __construct(protected FileManager $fileManager)
    {
        $this->cdnBaseUrl = ConfigQuery::read('cdn.documents-base-url', null);
    }

    /**
     * @return string root of the file cache directory in web space
     */
    abstract protected function getCacheDirFromWebRoot(): string;

    /**
     * @param string $url the fully qualified CDN URL that will be used to create doucments URL
     */
    public function setCdnBaseUrl(string $url): void
    {
        $this->cdnBaseUrl = $url;
    }

    /**
     * Clear the file cache. Is a subdirectory is specified, only this directory is cleared.
     * If no directory is specified, the whole cache is cleared.
     * Only files are deleted, directories will remain.
     */
    public function clearCache(CachedFileEvent $event): void
    {
        $path = $this->getCachePath($event->getCacheSubdirectory(), false);

        $this->clearDirectory($path);
    }

    /**
     * Recursively clears the specified directory.
     */
    protected function clearDirectory(string $path): void
    {
        $iterator = new \DirectoryIterator($path);

        /** @var \DirectoryIterator $fileinfo */
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDot()) {
                continue;
            }

            if ($fileinfo->isFile() || $fileinfo->isLink()) {
                @unlink($fileinfo->getPathname());
            } elseif ($fileinfo->isDir()) {
                $this->clearDirectory($fileinfo->getPathname());
            }
        }
    }

    /**
     * Return the absolute URL to the cached file.
     *
     * @param string $subdir        the subdirectory related to cache base
     * @param string $safe_filename the safe filename, as returned by getCacheFilePath()
     *
     * @return string the absolute URL to the cached file
     */
    protected function getCacheFileURL(string $subdir, string $safe_filename): string
    {
        $path = $this->getCachePathFromWebRoot($subdir);

        return URL::getInstance()->absoluteUrl(\sprintf('%s/%s', $path, $safe_filename), null, URL::PATH_TO_FILE, $this->cdnBaseUrl);
    }

    /**
     * Return the full path of the cached file.
     *
     * @param string      $subdir            the subdirectory related to cache base
     * @param string      $filename          the filename
     * @param bool        $forceOriginalFile if true, the original file path in the cache dir is returned
     * @param string|null $hashed_options    a hash of transformation options, or null if no transformations have been applied
     *
     * @return string the cache directory path relative to Web Root
     */
    protected function getCacheFilePath(string $subdir, string $filename, bool $forceOriginalFile = false, ?string $hashed_options = null): string
    {
        $path = $this->getCachePath($subdir);

        $safe_filename = preg_replace('[^:alnum:\\-\\._]', '-', strtolower(basename($filename)));

        // Keep original safe name if no tranformations are applied
        if ($forceOriginalFile || null === $hashed_options) {
            return \sprintf('%s/%s', $path, $safe_filename);
        }

        return \sprintf('%s/%s-%s', $path, $hashed_options, $safe_filename);
    }

    /**
     * Return the cache directory path relative to Web Root.
     *
     * @param string|null $subdir the subdirectory related to cache base, or null to get the cache directory only
     *
     * @return string the cache directory path relative to Web Root
     */
    protected function getCachePathFromWebRoot(?string $subdir = null): string
    {
        $cache_dir_from_web_root = $this->getCacheDirFromWebRoot();

        if (null !== $subdir) {
            $safe_subdir = basename($subdir);

            $path = \sprintf('%s/%s', $cache_dir_from_web_root, $safe_subdir);
        } else {
            $path = $cache_dir_from_web_root;
        }

        // Check if path is valid, e.g. in the cache dir
        return $path;
    }

    /**
     * Return the absolute cache directory path.
     *
     * @param string|null $subdir               the subdirectory related to cache base, or null to get the cache base directory
     * @param bool        $create_if_not_exists create the directory if it is not found
     *
     * @return string the absolute cache directory path
     *
     * @throws \RuntimeException         if cache directory cannot be created
     * @throws \InvalidArgumentException ii path is invalid, e.g. not in the cache dir
     */
    protected function getCachePath(?string $subdir = null, bool $create_if_not_exists = true): string
    {
        $cache_base = $this->getCachePathFromWebRoot($subdir);

        $web_root = rtrim(THELIA_WEB_DIR, '/');

        $path = \sprintf('%s/%s', $web_root, $cache_base);

        // Create directory (recursively) if it does not exists.
        if ($create_if_not_exists && !is_dir($path) && !mkdir($path, 0o777, true) && !is_dir($path)) {
            throw new \RuntimeException(\sprintf('Failed to create %s file in cache directory', $path));
        }

        // Check if path is valid, e.g. in the cache dir
        $cache_base = realpath(\sprintf('%s/%s', $web_root, $this->getCachePathFromWebRoot()));

        if (!str_starts_with(realpath($path), $cache_base)) {
            throw new \InvalidArgumentException(\sprintf('Invalid cache path %s, with subdirectory %s', $path, $subdir));
        }

        return $path;
    }

    /**
     * Take care of saving a file in the database and file storage.
     *
     * @param FileCreateOrUpdateEvent $event Image event
     *
     * @throws FileException|\Exception
     */
    public function saveFile(FileCreateOrUpdateEvent $event): void
    {
        $model = $event->getModel();
        if (null === $model) {
            return;
        }

        $model->setFile(\sprintf('tmp/%s', $event->getUploadedFile()?->getFilename()));

        $con = Propel::getWriteConnection(ProductImageTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $nbModifiedLines = $model->save($con);
            $event->setModel($model);

            if (!$nbModifiedLines) {
                throw new FileException(\sprintf('File "%s" (type %s) with parent id %s failed to be saved', $event->getParentName(), $model::class, $event->getParentId()));
            }

            $newUploadedFile = $this->fileManager->copyUploadedFile($event->getModel(), $event->getUploadedFile());

            $event->setUploadedFile($newUploadedFile);
            $con->commit();
        } catch (\Exception $exception) {
            $con->rollBack();

            throw $exception;
        }
    }

    /**
     * Take care of updating file in the database and file storage.
     *
     * @param FileCreateOrUpdateEvent $event Image event
     *
     * @throws FileException
     */
    public function updateFile(FileCreateOrUpdateEvent $event): void
    {
        $model = $event->getModel();
        $oldModel = $event->getOldModel();
        if (null === $model || null === $oldModel) {
            return;
        }

        $uploadedFile = $event->getUploadedFile();

        // Copy and save file
        if ($uploadedFile instanceof UploadedFile) {
            // Remove old picture file from file storage
            $url = $model->getUploadDir().'/'.$oldModel->getFile();
            unlink(str_replace('..', '', $url));
            $model->setFile('')->save();

            $newUploadedFile = $this->fileManager->copyUploadedFile($model, $uploadedFile);
            $event->setUploadedFile($newUploadedFile);
        }

        // Update image modifications
        $model->save();

        $event->setModel($model);
    }

    /**
     * Deleting file in the database and in storage.
     *
     * @param FileDeleteEvent $event Image event
     */
    public function deleteFile(FileDeleteEvent $event): void
    {
        $this->fileManager->deleteFile($event->getFileToDelete());
    }

    public function updatePosition(UpdateFilePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericUpdatePosition($event->getQuery(), $event, $dispatcher);
    }

    public function toggleVisibility(FileToggleVisibilityEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericToggleVisibility($event->getQuery(), $event, $dispatcher);
    }
}
