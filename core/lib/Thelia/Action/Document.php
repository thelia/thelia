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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Document\DocumentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Exception\DocumentException;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

/**
 * Document management actions. This class handles document processing an caching.
 *
 * Basically, documents are stored outside the web space (by default in local/media/documents),
 * and cached in the web space (by default in web/local/documents).
 *
 * In the documents caches directory, a subdirectory for documents categories (eg. product, category, folder, etc.) is
 * automatically created, and the cached document is created here. Plugin may use their own subdirectory as required.
 *
 * The cached document name contains a hash of the processing options, and the original (normalized) name of the document.
 *
 * A copy (or symbolic link, by default) of the original document is always created in the cache, so that the full
 * resolution document is always available.
 *
 * If a problem occurs, an DocumentException may be thrown.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class Document extends BaseCachedFile implements EventSubscriberInterface
{
    /** @var string Config key for document delivery mode */
    public const CONFIG_DELIVERY_MODE = 'original_document_delivery_mode';

    /**
     * Written at the root of the document cache for Apache. The web server serves the
     * published documents itself, so the headers the shop sets on its own answers never
     * reach them: a document is handed over as a download, except the types a browser
     * shows without running anything, and its type is never guessed from its content.
     * A file already there is left as it is.
     */
    public const CACHE_DIRECTORY_HTACCESS = <<<'HTACCESS'
        # Written by Thelia: documents are served as downloads, never as pages of the shop.
        <IfModule mod_headers.c>
            Header set X-Content-Type-Options "nosniff"
            Header set Content-Disposition "attachment"
            <FilesMatch "\.(?i:pdf|jpe?g|png|gif|webp|avif|txt)$">
                Header unset Content-Disposition
            </FilesMatch>
        </IfModule>

        HTACCESS;

    /**
     * @return string root of the document cache directory in web space
     */
    protected function getCacheDirFromWebRoot(): string
    {
        return ConfigQuery::read('document_cache_dir_from_web_root', 'cache'.DS.'documents');
    }

    /**
     * Process document and write the result in the document cache.
     *
     * When the original document is required, create either a symbolic link with the
     * original document in the cache dir, or copy it in the cache dir if it's not already done.
     *
     * This method updates the cache_file_path and file_url attributes of the event
     *
     * @param DocumentEvent $event Event
     *
     * @throws DocumentException
     * @throws \InvalidArgumentException , DocumentException
     */
    public function processDocument(DocumentEvent $event): void
    {
        $subdir = $event->getCacheSubdirectory();
        $sourceFile = $event->getSourceFilepath();

        if (null === $sourceFile) {
            throw new \InvalidArgumentException('Cache sub-directory and source file path cannot be null');
        }

        $originalDocumentPathInCache = $this->getCacheFilePath($subdir, $sourceFile, true);

        $this->protectCacheDirectory();

        if (!file_exists($originalDocumentPathInCache)) {
            if (!file_exists($sourceFile)) {
                throw new DocumentException(\sprintf('Source document file %s does not exists.', $sourceFile));
            }

            $mode = ConfigQuery::read(self::CONFIG_DELIVERY_MODE, 'symlink');

            if ('symlink' === $mode) {
                if (false === symlink($sourceFile, $originalDocumentPathInCache)) {
                    throw new DocumentException(\sprintf('Failed to create symbolic link for %s in %s document cache directory', basename($sourceFile), $subdir));
                }
            } elseif (false === @copy($sourceFile, $originalDocumentPathInCache)) {
                // mode = 'copy'
                throw new DocumentException(\sprintf('Failed to copy %s in %s document cache directory', basename($sourceFile), $subdir));
            }
        }

        // Compute the document URL
        $documentUrl = $this->getCacheFileURL($subdir, basename($originalDocumentPathInCache));

        // Update the event with file path and file URL
        $event->setDocumentPath($documentUrl);
        $event->setDocumentUrl(URL::getInstance()->absoluteUrl($documentUrl, null, URL::PATH_TO_FILE, $this->cdnBaseUrl));
    }

    /**
     * @throws DocumentException
     */
    private function protectCacheDirectory(): void
    {
        $htaccess = $this->getCachePath().DS.'.htaccess';

        if (file_exists($htaccess)) {
            return;
        }

        if (false === @file_put_contents($htaccess, self::CACHE_DIRECTORY_HTACCESS)) {
            throw new DocumentException(\sprintf('Failed to write %s in the document cache directory', basename($htaccess)));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::DOCUMENT_PROCESS => ['processDocument', 128],

            // Implemented in parent class BaseCachedFile
            TheliaEvents::DOCUMENT_CLEAR_CACHE => ['clearCache', 128],
            TheliaEvents::DOCUMENT_DELETE => ['deleteFile', 128],
            TheliaEvents::DOCUMENT_SAVE => ['saveFile', 128],
            TheliaEvents::DOCUMENT_UPDATE => ['updateFile', 128],
            TheliaEvents::DOCUMENT_UPDATE_POSITION => ['updatePosition', 128],
            TheliaEvents::DOCUMENT_TOGGLE_VISIBILITY => ['toggleVisibility', 128],
        ];
    }
}
