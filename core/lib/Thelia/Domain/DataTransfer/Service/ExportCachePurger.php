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

namespace Thelia\Domain\DataTransfer\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Thelia\Domain\DataTransfer\Export\ExportStorage;

class ExportCachePurger
{
    private const EXPORT_CACHE_MAX_AGE_DAYS = 1;

    /**
     * @param string|null $directory the export folder by default
     * @param bool        $dryRun    count the files that would be deleted, and delete nothing
     */
    public function purgeOldExportFiles(?string $directory = null, bool $dryRun = false): int
    {
        $directory ??= ExportStorage::directory();

        if (!is_dir($directory)) {
            return 0;
        }

        $finder = new Finder();
        $finder->files()->in($directory)->date('before '.self::EXPORT_CACHE_MAX_AGE_DAYS.' days ago');

        $fileSystem = new Filesystem();
        $deletedCount = 0;

        foreach ($finder as $oldExportFile) {
            if (!$dryRun) {
                // The link itself, never the file it points to.
                $fileSystem->remove($oldExportFile->getPathname());
            }
            ++$deletedCount;
        }

        return $deletedCount;
    }
}
