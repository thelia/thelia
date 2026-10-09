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
 * The one check of a path that must name a file of a folder, for the folders the shop
 * owns (the export folder, the import storage): what is served is the resolved file,
 * what is removed is the path itself, and only when both lie in the folder.
 */
final class FolderFile
{
    private function __construct()
    {
    }

    /**
     * The path, its links resolved, when it is a file of the folder. Null otherwise.
     */
    public static function resolve(string $directory, string $path): ?string
    {
        $folder = realpath($directory);
        $file = realpath($path);

        if (false === $folder || false === $file || !is_file($file) || !str_starts_with($file, $folder.\DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $file;
    }

    /**
     * The path to remove when it names a file of the folder from within the folder: a
     * link of the folder goes alone, never the file it points to, and nothing outside
     * the folder ever goes, a link included. Null otherwise.
     */
    public static function removable(string $directory, string $path): ?string
    {
        $folder = realpath($directory);
        $parent = realpath(\dirname($path));

        if (null === self::resolve($directory, $path) || false === $folder || false === $parent
            || ($parent !== $folder && !str_starts_with($parent, $folder.\DIRECTORY_SEPARATOR))
        ) {
            return null;
        }

        return $path;
    }
}
