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

namespace Thelia\Core\EventListener;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\File\FileModelInterface;
use Thelia\Core\File\LocalizedFileModelInterface;
use Thelia\Model\ConfigQuery;

/**
 * A deleted image or document leaves no copy of its files in the web space.
 *
 * The files a shop stores live outside the web root; what a visitor downloads is the
 * link or the copy published in the cache directory (`cache/images`, `cache/documents`),
 * the resized versions of an image, and the variants the image filters render
 * (`media/cache`). Deleting the stored file left all of them behind, served at the
 * same address as before.
 *
 * This runs ahead of the actions that delete the row: a translated image no longer
 * knows its files once its translations are gone.
 */
final readonly class DeletedMediaCopiesListener
{
    /**
     * Name of a resized copy: the hash of the transformation, then the name of the file,
     * its extension changed when the copy is converted to another format.
     */
    private const string RESIZED_COPY_PATTERN = '/^[0-9a-f]{32}-%s\.[^.]+$/';

    public function __construct(
        private CacheManager $imagineCacheManager,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::IMAGE_DELETE, priority: 256)]
    public function removeImageCopies(FileDeleteEvent $event): void
    {
        $model = $event->getFileToDelete();
        $files = self::storedFilesOf($model);

        $this->removeCopies(ConfigQuery::read('image_cache_dir_from_web_root', 'cache'.DS.'images'), $model, $files);

        $paths = [];

        foreach ($files as $file) {
            $path = basename($model->getUploadDir()).'/'.$file;
            $paths[] = $path;
            $paths[] = $path.'.webp';
        }

        // An empty list would make the filters drop their whole cache.
        if ([] !== $paths) {
            $this->imagineCacheManager->remove($paths);
        }
    }

    #[AsEventListener(event: TheliaEvents::DOCUMENT_DELETE, priority: 256)]
    public function removeDocumentCopies(FileDeleteEvent $event): void
    {
        $model = $event->getFileToDelete();

        $this->removeCopies(ConfigQuery::read('document_cache_dir_from_web_root', 'cache'.DS.'documents'), $model, self::storedFilesOf($model));
    }

    /**
     * @return list<string>
     */
    private static function storedFilesOf(FileModelInterface $model): array
    {
        $files = $model instanceof LocalizedFileModelInterface ? $model->getStoredFiles() : [$model->getFile()];

        return array_values(array_filter(array_map(basename(...), $files), static fn (string $file): bool => '' !== $file));
    }

    /**
     * The same file may have been published under several subdirectories of the cache:
     * the one of its item, and any other a caller asked for.
     *
     * @param list<string> $files
     */
    private function removeCopies(string $cacheDirFromWebRoot, FileModelInterface $model, array $files): void
    {
        $cacheDirectory = rtrim(THELIA_WEB_DIR, '/').'/'.$cacheDirFromWebRoot;
        $subdirectories = is_dir($cacheDirectory) ? glob($cacheDirectory.'/*', \GLOB_ONLYDIR) : [];

        foreach ($files as $file) {
            $name = strtolower($file);
            $stem = pathinfo($name, \PATHINFO_FILENAME);
            $resizedCopy = \sprintf(self::RESIZED_COPY_PATTERN, preg_quote($stem, '/'));
            $storedPath = basename($model->getUploadDir()).'/'.$file;

            foreach ($subdirectories ?: [] as $subdirectory) {
                $published = $subdirectory.'/'.$name;

                // A link is removed when it points at this very file: another item may
                // store a file of the same name.
                if (is_link($published) && str_ends_with((string) readlink($published), '/'.$storedPath)) {
                    unlink($published);
                } elseif (is_file($published) && !is_link($published)) {
                    unlink($published);
                }

                foreach (glob($subdirectory.'/'.str_repeat('?', 32).'-'.addcslashes($stem, '*?[]\\').'.*') ?: [] as $copy) {
                    if (1 === preg_match($resizedCopy, basename($copy)) && is_file($copy)) {
                        unlink($copy);
                    }
                }
            }
        }
    }
}
