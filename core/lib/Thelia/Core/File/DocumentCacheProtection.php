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

namespace Thelia\Core\File;

/**
 * The .htaccess written at the root of the document cache, for Apache.
 *
 * The web server serves the published documents itself, so the headers the shop sets
 * on its own answers never reach them: a document is handed over as a download, except
 * the types a browser shows without running anything, and its type is never guessed
 * from its content. The document cache writes it before publishing a document, and the
 * update writes it for the documents an earlier version published.
 */
final class DocumentCacheProtection
{
    public const HTACCESS = <<<'HTACCESS'
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
     * Writes the .htaccess in the given document cache directory, unless one is there
     * already: a file the shop wrote is left as it is.
     *
     * @return bool false when the file could not be written
     */
    public static function protect(string $cacheDirectory): bool
    {
        $htaccess = rtrim($cacheDirectory, '/\\').\DIRECTORY_SEPARATOR.'.htaccess';

        if (file_exists($htaccess)) {
            return true;
        }

        return false !== @file_put_contents($htaccess, self::HTACCESS);
    }
}
