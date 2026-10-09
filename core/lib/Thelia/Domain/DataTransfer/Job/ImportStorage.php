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
use Symfony\Component\Filesystem\Filesystem;
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
     * Whether $path, links resolved, is a file of this directory.
     */
    public function holds(string $path): bool
    {
        return null !== $this->resolve($path);
    }

    /**
     * The path, its links resolved, when it is a file of this directory. Null otherwise.
     */
    public function resolve(string $path): ?string
    {
        $directory = realpath($this->directory());
        $file = realpath($path);

        if (false === $directory || false === $file || !is_file($file) || !str_starts_with($file, $directory.\DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $file;
    }

    /**
     * Deletes the uploaded file of a job, when it is still there and is a file of this
     * directory: never a folder, never what a link points to.
     */
    public function discardFileOf(ImportJob $job): void
    {
        // Gone meanwhile (another run, the purge) is as good as deleted.
        if ($this->holds($this->pathOf($job))) {
            (new Filesystem())->remove($this->pathOf($job));
        }
    }
}
