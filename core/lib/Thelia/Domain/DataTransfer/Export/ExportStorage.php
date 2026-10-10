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

namespace Thelia\Domain\DataTransfer\Export;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Thelia\Core\File\FolderFile;
use Thelia\Log\Tlog;
use Thelia\Messenger\JobFailureMessage;

/**
 * Where the exports are written, read from and served: nothing outside it is ever
 * served or deleted on the word of a row, a listener or an export of a module.
 *
 * Static, as the exports that write their rows here are not services.
 */
final class ExportStorage
{
    private function __construct()
    {
    }

    public static function directory(): string
    {
        return THELIA_CACHE_DIR.'export';
    }

    /**
     * The path, its links resolved, when it is a file of the export folder. Null
     * otherwise.
     */
    public static function resolve(string $path): ?string
    {
        return FolderFile::resolve(self::directory(), $path);
    }

    /**
     * A new file of the export folder, readable by its owner and its group only from its
     * very creation: it holds the data of the customers.
     */
    public static function newPrivateFile(string $name): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\z/', $name) || str_contains($name, '..')) {
            throw new \InvalidArgumentException('The name of an export file is letters, digits, ".", "-" and "_", inside the export folder.');
        }

        self::ensureFolder();
        $path = self::directory().\DIRECTORY_SEPARATOR.$name;

        // Created private: "x" never opens a file or a link already there.
        $handle = FolderFile::writingPrivately(static fn () => @fopen($path, 'x'));

        if (false === $handle) {
            throw new IOException(\sprintf('The export file %s could not be created.', $name));
        }

        fclose($handle);

        // The belt, as the umask is the process's.
        FolderFile::makePrivateOrRemove($path);

        return $path;
    }

    /**
     * The export folder, made when it does not exist yet (FolderFile::FOLDER_MODE); one
     * made before keeps its mode.
     */
    public static function ensureFolder(): void
    {
        FolderFile::ensureFolder(self::directory());
    }

    /**
     * A file of the export folder that another tool wrote (an archiver), made as private
     * as the exports.
     */
    public static function makePrivate(string $path): void
    {
        $file = FolderFile::resolve(self::directory(), $path);

        if (null !== $file) {
            FolderFile::makePrivate($file);
        }
    }

    /**
     * A name of the folder's letters for what an export, or a module, calls itself:
     * spaces, accents and anything else become "_".
     */
    public static function safeName(string $name): string
    {
        // Cut to leave room, within the 255 bytes of a file name, for the date, the unique
        // part and the extension.
        $safe = trim(substr((string) preg_replace('/[^A-Za-z0-9_-]+/', '_', $name), 0, 100), '_-');

        return '' === $safe ? 'export' : $safe;
    }

    /**
     * A new file for the rows an export reads: a name of its own, as two workers may
     * run the same export at once.
     */
    public static function newRowsFile(string $exportName): string
    {
        return self::newPrivateFile(self::safeName($exportName).'-'.bin2hex(random_bytes(8)).'.json');
    }

    /**
     * Removes every file of the export folder among $paths but the one kept.
     *
     * @param list<string> $paths
     */
    public static function discardAllBut(array $paths, ?string $kept): void
    {
        $keptFile = null === $kept ? null : self::resolve($kept);

        foreach ($paths as $path) {
            if (self::resolve($path) !== $keptFile) {
                self::discard($path);
            }
        }
    }

    /**
     * Removes the path when it is a file of the export folder. A file that cannot go is
     * logged and left to the purge: it never takes the place of what the caller is
     * telling.
     */
    public static function discard(string $path): void
    {
        // The path itself: a link of the folder to another export goes alone.
        try {
            FolderFile::remove(self::directory(), $path);
        } catch (IOExceptionInterface $notRemoved) {
            Tlog::getInstance()->addWarning(\sprintf('An export file was not removed: %s', JobFailureMessage::forLog($notRemoved)));
        }
    }
}
