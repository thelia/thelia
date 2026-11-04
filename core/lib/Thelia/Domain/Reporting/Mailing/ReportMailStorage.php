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

namespace Thelia\Domain\Reporting\Mailing;

use Thelia\Core\File\FolderFile;

/**
 * Where the files of the reports sent as a link wait to be downloaded. Nothing outside
 * it is ever served or removed on the word of a request.
 */
final class ReportMailStorage
{
    public const string FILE_PATTERN = '[a-f0-9]{32}\.csv';

    private function __construct()
    {
    }

    public static function directory(): string
    {
        return THELIA_ROOT.'var/report-mail';
    }

    /**
     * The path, its links resolved, when it is a file of the folder. Null otherwise.
     */
    public static function resolve(string $file): ?string
    {
        if (1 !== preg_match('/^'.self::FILE_PATTERN.'\z/', $file)) {
            return null;
        }

        return FolderFile::resolve(self::directory(), self::directory().\DIRECTORY_SEPARATOR.$file);
    }

    /**
     * Moves a file made private (an export) into the folder under a name nobody can guess,
     * and gives that name.
     */
    public static function adopt(string $path): string
    {
        FolderFile::ensureFolder(self::directory());

        $name = bin2hex(random_bytes(16)).'.csv';
        $target = self::directory().\DIRECTORY_SEPARATOR.$name;

        if (!@rename($path, $target)) {
            throw new \RuntimeException('The file of a report could not be moved to the report folder.');
        }

        FolderFile::makePrivate($target);

        return $name;
    }

    public static function discard(string $file): void
    {
        $path = self::resolve($file);

        if (null !== $path) {
            FolderFile::remove(self::directory(), $path);
        }
    }

    /**
     * The files older than $days days: their link has expired.
     *
     * @return list<string> the paths
     */
    public static function expiredFiles(int $days): array
    {
        if (!is_dir(self::directory())) {
            return [];
        }

        $limit = time() - $days * 86400;
        $expired = [];

        foreach (new \DirectoryIterator(self::directory()) as $entry) {
            if ($entry->isFile() && !$entry->isLink() && $entry->getMTime() < $limit) {
                $expired[] = $entry->getPathname();
            }
        }

        return $expired;
    }
}
