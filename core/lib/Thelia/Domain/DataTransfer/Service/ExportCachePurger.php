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

    public function purgeOldExportFiles(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $finder = new Finder();
        $finder->files()->in($directory)->date('before '.self::EXPORT_CACHE_MAX_AGE_DAYS.' days ago');

        $fileSystem = new Filesystem();
        $deletedCount = 0;

        foreach ($finder as $oldExportFile) {
            $fileSystem->remove($oldExportFile->getRealPath());
            ++$deletedCount;
        }

        return $deletedCount;
    }
}
