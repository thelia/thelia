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
use Thelia\Domain\DataTransfer\Exception\JobRefusedException;
use Thelia\Domain\DataTransfer\ExportHandler;
use Thelia\Model\ExportJob;
use Thelia\Model\ExportJobQuery;

/**
 * Writes the file of one export job, and keeps its row telling how far it got.
 *
 * A finished export is never run again, and one being run is not run a second time
 * at once ({@see JobClaim}), so a job delivered twice writes one file. A job left
 * running by a worker that died, or one that failed and is replayed, starts over from
 * the first row. A failure is recorded on the row and the job goes
 * straight to the failure transport ({@see JobLifecycle}): running the same export again without changing
 * anything fails the same way, so it is not retried on its own.
 */
#[AsMessageHandler]
final readonly class RunExportJobHandler
{
    public function __construct(
        private ExportHandler $exportHandler,
        private SerializerManager $serializerManager,
        private ArchiverManager $archiverManager,
        private JobLifecycle $lifecycle,
    ) {
    }

    public function __invoke(RunExportJob $message): void
    {
        // Reading the row and taking it may fail too (the database gone): what is set
        // aside then says no more than any other failure.
        try {
            $job = ExportJobQuery::create()->findPk($message->exportJobId);
            $outcome = $job instanceof ExportJob ? $this->lifecycle->claimOrPostpone($job, $message) : null;
        } catch (UnrecoverableMessageHandlingException $setAside) {
            throw $setAside;
        } catch (\Throwable $exception) {
            $this->lifecycle->reject($message->describe(), $exception);
        }

        if (!$job instanceof ExportJob) {
            // Replayed after the purge took its row: nothing can run, and saying so
            // keeps the job among the failures instead of reporting it done.
            throw new UnrecoverableMessageHandlingException(\sprintf('Export job %d no longer exists.', $message->exportJobId));
        }

        if (ClaimOutcome::Owned !== $outcome) {
            return;
        }

        try {
            $job->setProcessedRows(0)->save();
            $this->run($job);
        } catch (\Throwable $exception) {
            $this->lifecycle->fail($job, $exception);
        }
    }

    private function run(ExportJob $job): void
    {
        $export = $job->getExport() ?? throw new JobRefusedException('The export of this job no longer exists.');
        // The format may have gone with its module since the export was asked for.
        if (!$this->serializerManager->has($job->getSerializer())) {
            throw new JobRefusedException(\sprintf('The format "%s" is no longer available on this server.', $job->getSerializer()));
        }

        $serializer = $this->serializerManager->get($job->getSerializer());
        $archiver = null;

        if (null !== $job->getArchiver()) {
            $archiver = $this->archiverManager->get($job->getArchiver(), true)
                ?? throw new JobRefusedException(\sprintf('The archiver "%s" is not available on this server.', $job->getArchiver()));
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
