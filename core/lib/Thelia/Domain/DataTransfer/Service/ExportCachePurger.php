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

class ExportCachePurger
{
    private const EXPORT_CACHE_MAX_AGE_DAYS = 1;

    /**
     * Where the exports are written, and the only folder an export is ever served from.
     */
    public static function directory(): string
    {
        return THELIA_CACHE_DIR.'export';
    }

    /**
     * The path, its links resolved, when it is a file of the export folder: the only kind
     * of path an export is ever served from or deleted by. Null otherwise.
     */
    public static function resolve(string $path): ?string
    {
        $directory = realpath(self::directory());
        $file = realpath($path);

        if (false === $directory || false === $file || !is_file($file) || !str_starts_with($file, $directory.\DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $file;
    }

    public function purgeOldExportFiles(string $directory): int
    {
        $deletedCount = 0;
        $fileSystem = new Filesystem();

        foreach ($this->oldExportFiles($directory) as $oldExportFile) {
            $fileSystem->remove($oldExportFile->getRealPath());
            ++$deletedCount;
        }

        return $deletedCount;
    }

    public function countOldExportFiles(string $directory): int
    {
        return iterator_count($this->oldExportFiles($directory));
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
