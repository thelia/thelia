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

use Thelia\Core\Archiver\ArchiverInterface;
use Thelia\Core\Archiver\ArchiverManager;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Domain\DataTransfer\ExportHandler;
use Thelia\Model\Export;
use Thelia\Model\ExportJob;
use Thelia\Model\Lang;

/**
 * Records an export and hands it to the job queue, so a large catalog no longer holds
 * the page that asked for it.
 *
 * Without a queue the job runs at once, in this call, and the export comes back
 * finished (or failed) exactly as it did when it ran in the page. With one, it comes
 * back queued, and the row tells how far it got until a worker is done with it.
 */
final readonly class ExportJobLauncher
{
    public function __construct(
        private ExportHandler $exportHandler,
        private SerializerManager $serializerManager,
        private ArchiverManager $archiverManager,
        private JobLifecycle $lifecycle,
    ) {
    }

    /**
     * @param array{start?: mixed, end?: mixed}|null $rangeDate as ExportHandler::export() takes it
     */
    public function launch(
        Export $export,
        string $serializerId,
        ?string $archiverId = null,
        ?Lang $language = null,
        bool $includeImages = false,
        bool $includeDocuments = false,
        ?array $rangeDate = null,
        ?int $adminId = null,
    ): ExportJob {
        // Refused here, in the request, rather than by a worker minutes later.
        $this->serializerManager->has($serializerId, true);

        if (null !== $archiverId && !$this->archiverManager->get($archiverId, true) instanceof ArchiverInterface) {
            throw new \InvalidArgumentException(\sprintf('The archiver "%s" is not available on this server.', $archiverId));
        }

        $rangeDate = $this->exportHandler->resolveRangeDate($rangeDate);

        $job = (new ExportJob())
            ->setExportId($export->getId())
            ->setAdminId($adminId)
            ->setStatus(JobStatus::QUEUED->value)
            ->setSerializer($serializerId)
            ->setArchiver($archiverId)
            ->setLangId($language?->getId())
            ->setIncludeImages($includeImages ? 1 : 0)
            ->setIncludeDocuments($includeDocuments ? 1 : 0)
            ->setRangeStart(self::date($rangeDate['start'] ?? null))
            ->setRangeEnd(self::date($rangeDate['end'] ?? null));
        $job->save();

        $this->lifecycle->dispatch($job, new RunExportJob($job->getId()));

        return $job;
    }

    private static function date(mixed $value): ?\DateTimeInterface
    {
        return $value instanceof \DateTimeInterface ? $value : null;
    }
}
