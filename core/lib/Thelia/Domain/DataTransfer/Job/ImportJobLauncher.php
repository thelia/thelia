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
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
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

    public function __construct(
        private ImportHandler $importHandler,
        private MessageBusInterface $bus,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDirectory,
    ) {
    }

    public function launch(Import $import, File $file, string $originalName, ?Lang $language = null, ?int $adminId = null): ImportJob
    {
        // Refused here, in the request, rather than by a worker minutes later.
        $this->importHandler->validateUpload($originalName);

        $directory = $this->projectDirectory.\DIRECTORY_SEPARATOR.self::STORAGE_DIRECTORY.\DIRECTORY_SEPARATOR.(new \DateTime())->format('Ymd');
        $stored = $file->move($directory, uniqid('', true).'-'.basename($originalName));

        $job = (new ImportJob())
            ->setImportId($import->getId())
            ->setAdminId($adminId)
            ->setStatus(JobStatus::QUEUED->value)
            ->setLangId($language?->getId())
            ->setFilePath($stored->getPathname())
            ->setFileName(basename($originalName));
        $job->save();

        try {
            $this->bus->dispatch(new RunImportJob($job->getId()));
        } catch (HandlerFailedException) {
            // Run at once, without a queue: the handler has written why on the row.
        }

        $job->reload();

        return $job;
    }
}
