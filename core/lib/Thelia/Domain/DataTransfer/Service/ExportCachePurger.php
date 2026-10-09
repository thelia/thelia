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
     * Deletes the export files older than a day, and tells how many went. A link in the
     * folder goes by itself, never the file it points to.
     *
     * @param string|null $directory the export folder by default
     */
    public function purgeOldExportFiles(?string $directory = null): int
    {
        $deletedCount = 0;
        $fileSystem = new Filesystem();

        foreach ($this->oldExportFiles($directory ?? ExportStorage::directory()) as $oldExportFile) {
            $fileSystem->remove($oldExportFile->getPathname());
            ++$deletedCount;
        }

        return $deletedCount;
    }

    /**
     * How many files a purge would delete, for a dry run: nothing is written.
     *
     * @param string|null $directory the export folder by default
     */
    public function countOldExportFiles(?string $directory = null): int
    {
        return iterator_count($this->oldExportFiles($directory ?? ExportStorage::directory()));
    }

    /**
     * @return \Traversable<\SplFileInfo>
     */
    private function oldExportFiles(string $directory): \Traversable
    {
        if (!is_dir($directory)) {
            return new \EmptyIterator();
        }

        return (new Finder())->files()->in($directory)->date('before '.self::EXPORT_CACHE_MAX_AGE_DAYS.' days ago')->getIterator();
    }
}
