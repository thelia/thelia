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

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Thelia\Core\Archiver\ArchiverInterface;
use Thelia\Core\Archiver\ArchiverManager;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Domain\DataTransfer\ExportHandler;
use Thelia\Log\Tlog;
use Thelia\Model\ExportJob;
use Thelia\Model\ExportJobQuery;

/**
 * Writes the file of one export job, and keeps its row telling how far it got.
 *
 * A finished export is never run again, so a job delivered twice writes one file. A
 * job left running by a worker that was stopped, or one that failed and is replayed,
 * starts over from the first row. A failure is recorded on the row and the job goes
 * straight to the failure transport: running the same export again without changing
 * anything fails the same way, so it is not retried on its own.
 */
#[AsMessageHandler]
final readonly class RunExportJobHandler
{
    public function __construct(
        private ExportHandler $exportHandler,
        private SerializerManager $serializerManager,
        private ArchiverManager $archiverManager,
    ) {
    }

    public function __invoke(RunExportJob $message): void
    {
        $job = ExportJobQuery::create()->findPk($message->exportJobId);

        if (!$job instanceof ExportJob || JobStatus::DONE === $job->getJobStatus()) {
            return;
        }

        $job->setStatus(JobStatus::RUNNING->value)
            ->setStartedAt(new \DateTime())
            ->setFinishedAt(null)
            ->setProcessedRows(0)
            ->setError(null)
            ->save();

        try {
            $this->run($job);
        } catch (\Throwable $exception) {
            Tlog::getInstance()->addError(\sprintf('Export job %d failed: %s', $job->getId(), $exception->getMessage()));

            $job->setStatus(JobStatus::FAILED->value)
                ->setError(mb_substr($exception->getMessage(), 0, 2000))
                ->setFinishedAt(new \DateTime())
                ->save();

            throw new UnrecoverableMessageHandlingException(\sprintf('Export job %d failed: %s', $job->getId(), $exception->getMessage()), 0, $exception);
        }
    }

    private function run(ExportJob $job): void
    {
        $export = $job->getExport() ?? throw new \RuntimeException('The export of this job no longer exists.');
        $serializer = $this->serializerManager->get($job->getSerializer());
        $archiver = null;

        if (null !== $job->getArchiver()) {
            $archiver = $this->archiverManager->get($job->getArchiver(), true)
                ?? throw new \RuntimeException(\sprintf('The archiver "%s" is not available on this server.', $job->getArchiver()));
        }

        $rangeDate = null;
        if (null !== $job->getRangeStart() || null !== $job->getRangeEnd()) {
            $rangeDate = ['start' => $job->getRangeStart(), 'end' => $job->getRangeEnd()];
        }

        $event = $this->exportHandler->export(
            $export,
            $serializer,
            $archiver,
            $job->getLang(),
            1 === $job->getIncludeImages(),
            1 === $job->getIncludeDocuments(),
            $rangeDate,
            static function (int $rows) use ($job): void {
                $job->setProcessedRows($rows)->save();
            },
        );

        $extension = $archiver instanceof ArchiverInterface ? $archiver->getExtension() : $serializer->getExtension();

        $job->setStatus(JobStatus::DONE->value)
            ->setFilePath($event->getFilePath())
            ->setFileName($event->getExport()->getFileName().'.'.$extension)
            ->setFinishedAt(new \DateTime())
            ->save();
    }
}
