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

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The one check of a path that must name a file of a folder, for the folders the shop
 * owns (the export folder, the import storage): what is served is the resolved file,
 * what is removed is the path itself, and only when both lie in the folder.
 *
 * It assumes the folder is written by the account of the shop alone: a path changed
 * between the check and its use could only be changed by that account.
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
     * Whether the path may be removed: it names a file of the folder from within the
     * folder. A link of the folder goes alone, never the file it points to, and nothing
     * outside the folder ever goes, a link included.
     */
    public static function isRemovable(string $directory, string $path): bool
    {
        $folder = realpath($directory);
        $parent = realpath(\dirname($path));

        return null !== self::resolve($directory, $path) && false !== $folder && false !== $parent
            && ($parent === $folder || str_starts_with($parent, $folder.\DIRECTORY_SEPARATOR));
    }

    /**
     * Removes the path when it may be: true once it is gone.
     *
     * @throws IOExceptionInterface when the file system refuses
     */
    public static function remove(string $directory, string $path): bool
    {
        if (!self::isRemovable($directory, $path)) {
            return false;
        }

        (new Filesystem())->remove($path);

        return true;
    }
}
