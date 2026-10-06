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
use Thelia\Domain\DataTransfer\ImportHandler;
use Thelia\Model\Import;
use Thelia\Model\ImportJob;
use Thelia\Model\Lang;

/**
 * Records an import and hands it to the job queue.
 *
 * The uploaded file is moved out of the request into var/data-transfer/import, not
 * into the cache: a deployment empties the cache, and the import would lose its file
 * before a worker reached it. Without a queue the import runs in this call and comes
 * back finished, with the rows it changed and the ones it refused, as it did in the
 * page. With one it comes back queued.
 */
final readonly class ImportJobLauncher
{
    public const STORAGE_DIRECTORY = 'var/data-transfer/import';

    private const MAX_NAME_LENGTH = 100;

    public function __construct(
        private ImportHandler $importHandler,
        private JobLifecycle $lifecycle,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDirectory,
    ) {
    }

    public function launch(Import $import, File $file, string $originalName, ?Lang $language = null, ?int $adminId = null): ImportJob
    {
        // Refused here, in the request, rather than by a worker minutes later.
        $this->importHandler->validateUpload($originalName, $file);

        $relativeDirectory = self::STORAGE_DIRECTORY.'/'.(new \DateTime())->format('Ymd');
        $stored = $file->move(
            $this->projectDirectory.\DIRECTORY_SEPARATOR.$relativeDirectory,
            uniqid('', true).'-'.self::shortName($originalName),
        );
        $relativePath = $relativeDirectory.'/'.$stored->getFilename();

        try {
            $job = (new ImportJob())
                ->setImportId($import->getId())
                ->setAdminId($adminId)
                ->setStatus(JobStatus::QUEUED->value)
                ->setLangId($language?->getId())
                ->setFilePath($relativePath)
                ->setFileName(self::shortName($originalName));
            $job->save();
        } catch (\Throwable $exception) {
            // The file holds what was uploaded, personal data included: it never stays
            // behind without a row that the purge would find it by.
            unlink($stored->getPathname());

            throw $exception;
        }

        try {
            $this->lifecycle->dispatch($job, new RunImportJob($job->getId()));
        } catch (\Throwable $exception) {
            // The queue refused the job: the row is failed, and the file goes with it.
            if (is_file($stored->getPathname())) {
                unlink($stored->getPathname());
            }

            throw $exception;
        }

        return $job;
    }

    /**
     * The uploaded name, cut to keep its extension and the stored path short.
     */
    private static function shortName(string $originalName): string
    {
        $name = basename($originalName);
        $extension = pathinfo($name, \PATHINFO_EXTENSION);
        $stem = '' === $extension ? $name : substr($name, 0, -\strlen($extension) - 1);

        return mb_substr($stem, 0, self::MAX_NAME_LENGTH).('' === $extension ? '' : '.'.mb_substr($extension, 0, 10));
    }
}
