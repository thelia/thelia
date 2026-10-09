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
 * what is removed is the path itself, and only when both lie in the folder. And the one
 * mode of what is written there: the files hold the data of the customers.
 *
 * It assumes the folder is written by the accounts of the shop alone: a path changed
 * between the check and its use could only be changed by one of them.
 */
final class FolderFile
{
    /**
     * A file of the shop: read by its owner and its group, the accounts the web server
     * and the workers run under.
     */
    public const FILE_MODE = 0o640;

    /**
     * A folder of the shop: the web server writes the import a worker deletes, a worker
     * writes the export the web server serves, and the purge removes what either left.
     * Under one group, each must write where the other did.
     */
    public const FOLDER_MODE = 0o770;

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
     * Removes the path when it may be: true when it removed something, false for a path
     * that is not a file of the folder, or is gone already.
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

    /**
     * The folder, and the folders above it, made with FOLDER_MODE when they do not exist
     * yet. One made before keeps the mode its owner gave it: the files inside are private
     * by their own mode, and the account running this may not be the one that owns the
     * folder.
     *
     * @throws IOExceptionInterface when the folder cannot be made
     */
    public static function ensureFolder(string $directory): void
    {
        self::writingPrivately(static fn () => (new Filesystem())->mkdir($directory, self::FOLDER_MODE), ~self::FOLDER_MODE & 0o777);
    }

    /**
     * Runs $write with the umask that makes what it creates private from its very
     * creation (FILE_MODE at most): an account that opened a file in between would keep
     * reading it. The umask is the process's and is given back whatever happens.
     *
     * @template T
     *
     * @param \Closure(): T $write
     *
     * @return T
     */
    public static function writingPrivately(\Closure $write, int $umask = ~self::FILE_MODE &0o777): mixed
    {
        $previousUmask = umask($umask);

        try {
            return $write();
        } finally {
            umask($previousUmask);
        }
    }

    /**
     * The file made FILE_MODE: for one written under the umask of the process (an
     * archiver, an upload moved), or as a belt for one created privately, since the umask
     * is the process's and a thread of a threaded server may have changed it.
     *
     * @throws IOExceptionInterface when the file system refuses
     */
    public static function makePrivate(string $file): void
    {
        (new Filesystem())->chmod($file, self::FILE_MODE);
    }
}
