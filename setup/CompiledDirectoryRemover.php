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

namespace Thelia\Setup;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Removes the compiled container or the generated Propel models of the previous
 * release before update.php boots on the new code.
 *
 * On a live shop these directories are written by the web server user, and the
 * script often runs as another one. Deleting a file needs write access to the
 * directory that holds it, which that user rarely has, while renaming the top
 * directory only needs write access to its parent. So the directory is renamed
 * first, which is enough to keep the kernel from loading it again, and deleted
 * afterwards as far as the permissions allow.
 */
final readonly class CompiledDirectoryRemover
{
    public function __construct(
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @return string|null the directory where the files that could not be deleted
     *                     were left, null when everything was deleted
     *
     * @throws \RuntimeException when the directory cannot be moved out of the way,
     *                           in which case nothing was changed
     */
    public function remove(string $directory): ?string
    {
        $movedDirectory = $directory.'.previous-'.bin2hex(random_bytes(4));
        $renameError = null;

        set_error_handler(static function (int $type, string $message) use (&$renameError): bool {
            $renameError = $message;

            return true;
        });

        try {
            $moved = rename($directory, $movedDirectory);
        } finally {
            restore_error_handler();
        }

        if (!$moved) {
            $owner = self::ownerOf($directory);

            $message = \sprintf(
                '%s holds the compiled files of the previous release and cannot be moved out of the way (%s). '
                .'Remove it as %s, then run this script again: sudo -u %s rm -rf %s',
                $directory,
                $renameError ?? 'rename failed',
                $owner,
                $owner,
                escapeshellarg($directory),
            );

            throw new \RuntimeException($message);
        }

        try {
            // The content first, then the directory itself: remove() renames a
            // directory before emptying it, and the files it cannot delete would end
            // up in a sibling with a random name instead of the one reported here.
            $this->filesystem->remove(new \FilesystemIterator(
                $movedDirectory,
                \FilesystemIterator::CURRENT_AS_PATHNAME | \FilesystemIterator::SKIP_DOTS,
            ));
            $this->filesystem->remove($movedDirectory);
        } catch (IOException) {
            return $movedDirectory;
        }

        return null;
    }

    /**
     * The name of the user that owns a file, for the command the message suggests.
     */
    public static function ownerOf(string $path): string
    {
        $uid = file_exists($path) ? fileowner($path) : false;

        if (false === $uid) {
            return 'the web server user';
        }

        if (\function_exists('posix_getpwuid')) {
            $user = posix_getpwuid($uid);

            if (\is_array($user)) {
                return $user['name'];
            }
        }

        // sudo -u accepts a numeric user id prefixed with #.
        return '#'.$uid;
    }
}
