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
     * Whether $path, links resolved, lies inside this directory.
     */
    public function holds(string $path): bool
    {
        $directory = realpath($this->directory());
        $file = realpath($path);

        return false !== $directory && false !== $file && str_starts_with($file, $directory.\DIRECTORY_SEPARATOR);
    }

    /**
     * Deletes the uploaded file of a job, when it is still there and lies inside this
     * directory.
     */
    public function discardFileOf(ImportJob $job): void
    {
        $path = $this->pathOf($job);

        // Gone meanwhile (another run, the purge) is as good as deleted.
        if ($this->holds($path)) {
            (new Filesystem())->remove($path);
        }
    }
}
