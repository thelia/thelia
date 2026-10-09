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

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
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
        $directory = realpath(self::directory());
        $file = realpath($path);

        if (false === $directory || false === $file || !is_file($file) || !str_starts_with($file, $directory.\DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $file;
    }

    /**
     * A new file, readable by its owner only, for the rows an export reads: a name of
     * its own, as two workers may run the same export at once.
     */
    public static function rowsFile(string $exportName): string
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir(self::directory());
        $path = self::directory().\DIRECTORY_SEPARATOR.$exportName.'-'.bin2hex(random_bytes(8)).'.json';
        $filesystem->touch($path);
        $filesystem->chmod($path, 0o600);

        return $path;
    }

    /**
     * Removes the path when it is a file of the export folder. A file that cannot go is
     * logged and left to the purge: it never takes the place of what the caller is
     * telling.
     */
    public static function discard(string $path): void
    {
        $file = self::resolve($path);

        if (null === $file) {
            return;
        }

        try {
            (new Filesystem())->remove($file);
        } catch (IOExceptionInterface $notRemoved) {
            Tlog::getInstance()->addWarning(\sprintf('An export file was not removed: %s', JobFailureMessage::forLog($notRemoved)));
        }
    }
}
