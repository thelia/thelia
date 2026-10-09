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

namespace Thelia\Domain\DataTransfer\Job;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;
use Thelia\Core\File\FolderFile;
use Thelia\Model\ImportJob;

/**
 * Where the uploaded files of the import jobs wait for their worker.
 *
 * Out of the cache, which a deployment empties before a worker reaches the file. A
 * row keeps a path relative to the project, so it stays short and survives the
 * project being moved. Nothing outside this directory is ever deleted on the word of
 * a row.
 */
final readonly class ImportStorage
{
    public const DIRECTORY = 'var/data-transfer/import';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDirectory,
    ) {
    }

    public function directory(): string
    {
        return $this->projectDirectory.\DIRECTORY_SEPARATOR.self::DIRECTORY;
    }

    /**
     * Moves an uploaded file into the storage, in the folder of the day, as private as
     * the files of the shop (FolderFile::FILE_MODE): it holds what was uploaded, personal
     * data included. A file that could not be made so is not left behind.
     */
    public function store(File $upload, string $name): File
    {
        $folder = $this->directory().\DIRECTORY_SEPARATOR.(new \DateTime())->format('Ymd');
        FolderFile::ensureFolder($folder);
        $stored = FolderFile::writingPrivately(static fn (): File => $upload->move($folder, $name));
        FolderFile::makePrivateOrRemove($stored->getPathname());

        return $stored;
    }

    /**
     * Where the uploaded file of a job is on this server.
     */
    public function pathOf(ImportJob $job): string
    {
        $path = (string) $job->getFilePath();

        return str_starts_with($path, '/') ? $path : $this->projectDirectory.\DIRECTORY_SEPARATOR.$path;
    }

    /**
     * The path of $absolutePath relative to the project, as a row keeps it.
     */
    public function relativePathOf(string $absolutePath): string
    {
        return ltrim(substr($absolutePath, \strlen($this->projectDirectory)), \DIRECTORY_SEPARATOR);
    }

    /**
     * The path, its links resolved, when it is a file of this directory. Null otherwise.
     */
    public function resolve(string $path): ?string
    {
        return FolderFile::resolve($this->directory(), $path);
    }

    /**
     * Deletes the uploaded file of a job, when it is still there and is a file of this
     * directory: never a folder, never what a link points to.
     */
    public function discardFileOf(ImportJob $job): void
    {
        // Gone meanwhile (another run, the purge) is as good as deleted.
        FolderFile::remove($this->directory(), $this->pathOf($job));
    }
}
