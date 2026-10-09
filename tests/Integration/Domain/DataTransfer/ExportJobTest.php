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

namespace Thelia\Tests\Integration\Domain\DataTransfer;

use Propel\Runtime\Propel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Core\Archiver\ArchiverInterface;
use Thelia\Core\Archiver\ArchiverManager;
use Thelia\Core\Event\ExportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Domain\DataTransfer\DataTransferProgress;
use Thelia\Domain\DataTransfer\EventListener\RemovedJobRowListener;
use Thelia\Domain\DataTransfer\Exception\JobRefusedException;
use Thelia\Domain\DataTransfer\ExportHandler;
use Thelia\Domain\DataTransfer\Job\ExportJobLauncher;
use Thelia\Domain\DataTransfer\Job\JobClaim;
use Thelia\Domain\DataTransfer\Job\JobLifecycle;
use Thelia\Domain\DataTransfer\Job\JobStatus;
use Thelia\Domain\DataTransfer\Job\RunExportJob;
use Thelia\Domain\DataTransfer\Job\RunExportJobHandler;
use Thelia\Domain\DataTransfer\Service\ExportCachePurger;
use Thelia\Messenger\Event\FailedJobRemovedEvent;
use Thelia\Messenger\JobFailureMessage;
use Thelia\Messenger\JobSetAsideException;
use Thelia\Messenger\Transport\ConfiguredQueues;
use Thelia\Messenger\Transport\ShopDatabaseConnection;
use Thelia\Model\Export;
use Thelia\Model\ExportJobQuery;
use Thelia\Model\ExportQuery;
use Thelia\Model\Lang;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\DataTransfer\ImageHeavyExport;
use Thelia\Tests\Support\DataTransfer\ModuleWrittenExportHandler;

/**
 * An export asked for in the back office is a job: without a queue it runs in the
 * request as it always did, with one it waits for a worker, and either way its row
 * says how far it got and where its file is.
 */
final class ExportJobTest extends IntegrationTestCase
{
    private const SERIALIZER = 'thelia.csv';

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        // The order export refuses to write a file with no row in it.
        $this->createFixtureFactory()->order();
        $this->createFixtureFactory()->order();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    /**
     * A shop with no queue runs the job in the request: the export comes back
     * finished, with the rows written and the file on disk.
     */
    public function testWithoutAQueueTheExportIsFinishedWhenTheLauncherReturns(): void
    {
        $job = $this->getService(ExportJobLauncher::class)->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $this->files[] = (string) $job->getFilePath();

        self::assertSame(JobStatus::DONE, $job->getJobStatus());
        self::assertGreaterThanOrEqual(2, $job->getProcessedRows());
        self::assertFileExists((string) $job->getFilePath());
        self::assertStringEndsWith('.csv', (string) $job->getFileName());
        self::assertNotNull($job->getFinishedAt());
    }

    /**
     * A job whose language is gone (deleted while the job waited, its row then holds no
     * language) is written in the default language.
     */
    public function testAnExportWithoutALanguageIsWrittenInTheDefaultOne(): void
    {
        $job = $this->getService(ExportJobLauncher::class)->launch($this->ordersExport(), self::SERIALIZER);
        $this->files[] = (string) $job->getFilePath();

        self::assertSame(JobStatus::DONE, $job->getJobStatus(), (string) $job->getError());
    }

    /**
     * With a queue, the request only records the job: nothing is written until a
     * worker runs it.
     */
    public function testWithAQueueTheExportWaitsForAWorker(): void
    {
        $queue = $this->queue();

        $job = $this->launcherWith($queue)->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage(), adminId: null);

        self::assertSame(JobStatus::QUEUED, $job->getJobStatus());
        self::assertNull($job->getFilePath());
        self::assertCount(1, $queue->kept);
        self::assertInstanceOf(RunExportJob::class, $queue->kept[0]);
        self::assertSame($job->getId(), $queue->kept[0]->exportJobId);

        $this->handler()(new RunExportJob($job->getId()));
        $job->reload();
        $this->files[] = (string) $job->getFilePath();

        self::assertSame(JobStatus::DONE, $job->getJobStatus());
        self::assertFileExists((string) $job->getFilePath());
    }

    /**
     * A queue may deliver a job twice: a finished export is not written again.
     */
    public function testAFinishedExportIsNotRunAgain(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $this->handler()(new RunExportJob($job->getId()));
        $job->reload();
        $firstFile = (string) $job->getFilePath();
        $this->files[] = $firstFile;

        $this->handler()(new RunExportJob($job->getId()));
        $job->reload();

        self::assertSame($firstFile, $job->getFilePath());
    }

    /**
     * Running the same export again fails the same way: the failure is written on the
     * row for the administrator, and the job is not retried on its own.
     */
    public function testAFailedExportSaysWhyAndIsNotRetriedOnItsOwn(): void
    {
        $export = $this->ordersExport();
        $job = $this->launcherWith($this->queue())->launch($export, self::SERIALIZER, language: Lang::getDefaultLanguage());
        $export->setHandleClass('Vendor\\Removed\\Module\\Export')->save($this->getPropelConnection());

        try {
            $this->handler()(new RunExportJob($job->getId()));
            self::fail('A failed export must reach the failure transport.');
        } catch (UnrecoverableMessageHandlingException) {
        }

        $job->reload();
        self::assertSame(JobStatus::FAILED, $job->getJobStatus());
        self::assertStringContainsString('Vendor\\Removed\\Module\\Export', (string) $job->getError());
        self::assertNotNull($job->getFinishedAt());
    }

    public function testAFailedExportReplayedOnceItCanRunIsWritten(): void
    {
        $export = $this->ordersExport();
        $handleClass = $export->getHandleClass();
        $job = $this->launcherWith($this->queue())->launch($export, self::SERIALIZER, language: Lang::getDefaultLanguage());
        $export->setHandleClass('Vendor\\Removed\\Module\\Export')->save($this->getPropelConnection());

        try {
            $this->handler()(new RunExportJob($job->getId()));
        } catch (UnrecoverableMessageHandlingException) {
        }

        $export->setHandleClass($handleClass)->save($this->getPropelConnection());
        $this->handler()(new RunExportJob($job->getId()));
        $job->reload();
        $this->files[] = (string) $job->getFilePath();

        self::assertSame(JobStatus::DONE, $job->getJobStatus());
        self::assertNull($job->getError());
    }

    /**
     * Two workers handed the same job: the second finds it taken and leaves it alone,
     * but looks again later rather than dropping the message. Dropped, a job whose
     * worker dies afterwards would stay running forever.
     */
    public function testAJobRunningElsewhereIsLookedAtAgainLater(): void
    {
        $queue = $this->queue();
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $job->setStatus(JobStatus::RUNNING->value)->setStartedAt(new \DateTime('-5 minutes'))->save($this->getPropelConnection());

        $this->handlerWith($queue)(new RunExportJob($job->getId()));
        $job->reload();

        self::assertSame(JobStatus::RUNNING, $job->getJobStatus());
        self::assertNull($job->getFilePath());
        self::assertCount(1, $queue->kept);
        self::assertEquals(new RunExportJob($job->getId(), 1), $queue->kept[0]);
        self::assertEquals([new DelayStamp(JobLifecycle::POSTPONE_DELAY_SECONDS * 1000)], $queue->stamps[0]);
    }

    public function testAJobStillRunningAfterEveryCheckIsSetAside(): void
    {
        $queue = $this->queue();
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $job->setStatus(JobStatus::RUNNING->value)->save($this->getPropelConnection());

        try {
            $this->handlerWith($queue)(new RunExportJob($job->getId(), JobLifecycle::MAX_POSTPONEMENTS));
            self::fail('After every check, the message is set aside where the administrator sees it.');
        } catch (UnrecoverableMessageHandlingException) {
        }

        self::assertCount(0, $queue->kept);
        $job->reload();
        self::assertSame(JobStatus::RUNNING, $job->getJobStatus());
    }

    /**
     * A message looking again at a job that failed meanwhile leaves it to the
     * administrator: it never restarts a failed job on its own.
     */
    public function testALookAgainNeverRestartsAJobThatFailedMeanwhile(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $job->setStatus(JobStatus::FAILED->value)->setError('The export failed.')->save($this->getPropelConnection());

        $this->handlerWith($this->queue())(new RunExportJob($job->getId(), 1));
        $job->reload();

        self::assertSame(JobStatus::FAILED, $job->getJobStatus());
        self::assertNull($job->getFilePath());

        // Replayed by the administrator, the original message takes it.
        $this->handlerWith($this->queue())(new RunExportJob($job->getId()));
        $job->reload();
        $this->files[] = (string) $job->getFilePath();
        self::assertSame(JobStatus::DONE, $job->getJobStatus());
    }

    /**
     * Without a queue, looking again would run the job at once, in the same call, over
     * and over: the run that holds the job finishes it.
     */
    public function testWithoutAQueueAJobRunningElsewhereIsNotLookedAtAgain(): void
    {
        $bus = $this->queue();
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $job->setStatus(JobStatus::RUNNING->value)->save($this->getPropelConnection());

        $handler = new RunExportJobHandler(
            $this->getService(ExportHandler::class),
            $this->getService(SerializerManager::class),
            $this->getService(ArchiverManager::class),
            $this->lifecycle($bus, 'sync://'),
        );
        $handler(new RunExportJob($job->getId()));

        self::assertCount(0, $bus->kept);
    }

    /**
     * A finished job delivered again is simply acknowledged: nothing to look at later.
     */
    public function testAFinishedJobDeliveredAgainIsNotLookedAtAgain(): void
    {
        $queue = $this->queue();
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $job->setStatus(JobStatus::DONE->value)->save($this->getPropelConnection());

        $this->handlerWith($queue)(new RunExportJob($job->getId()));

        self::assertCount(0, $queue->kept);
    }

    /**
     * The silence after which a running job is taken again is the redeliver timeout of
     * the transport, counted from its last sign of life.
     */
    public function testAJobIsTakenAgainOnlyOnceSilentForTheRedeliverTimeout(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $claim = new JobClaim();

        $job->setStatus(JobStatus::RUNNING->value)->setUpdatedAt(new \DateTime(\sprintf('-%d seconds', JobClaim::STALE_AFTER_SECONDS - 60)))->save($this->getPropelConnection());
        self::assertFalse($claim->claim('export_job', $job->getId()));

        $job->setUpdatedAt(new \DateTime(\sprintf('-%d seconds', JobClaim::STALE_AFTER_SECONDS + 60)))->save($this->getPropelConnection());
        self::assertTrue($claim->claim('export_job', $job->getId()));
    }

    /**
     * A worker that died left the job running: once the transport hands it again,
     * past the redeliver timeout, it runs.
     */
    public function testAJobLeftRunningByAWorkerThatDiedRunsAgain(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $job->setStatus(JobStatus::RUNNING->value)->setStartedAt(new \DateTime('-2 hours'))->setUpdatedAt(new \DateTime('-2 hours'))->save($this->getPropelConnection());

        $this->handler()(new RunExportJob($job->getId()));
        $job->reload();
        $this->files[] = (string) $job->getFilePath();

        self::assertSame(JobStatus::DONE, $job->getJobStatus());
    }

    /**
     * A long export started two hours ago that still reports its progress is alive:
     * it is not taken from under the worker writing it.
     */
    public function testALongExportThatStillWritesIsNotTakenFromItsWorker(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $job->setStatus(JobStatus::RUNNING->value)->setStartedAt(new \DateTime('-2 hours'))->setUpdatedAt(new \DateTime('-1 minute'))->save($this->getPropelConnection());

        $this->handler()(new RunExportJob($job->getId()));
        $job->reload();

        self::assertSame(JobStatus::RUNNING, $job->getJobStatus());
        self::assertNull($job->getFilePath());
    }

    /**
     * Replayed after the purge took its row, the job says so and stays among the
     * failures rather than passing for done.
     */
    public function testAJobWhoseRowIsGoneFailsForGood(): void
    {
        $this->expectException(UnrecoverableMessageHandlingException::class);

        $this->handler()(new RunExportJob(999999999));
    }

    public function testAJobTheQueueRefusesIsRecordedAsFailed(): void
    {
        $refusingQueue = new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw new \RuntimeException('The queue server is unreachable.');
            }
        };

        try {
            $this->launcherWith($refusingQueue)->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
            self::fail('The caller must learn the export was not queued.');
        } catch (\RuntimeException) {
        }

        $job = ExportJobQuery::create()->orderById('desc')->findOne();
        self::assertNotNull($job);
        self::assertSame(JobStatus::FAILED, $job->getJobStatus());
        // The queue's own words may name a server: they go to the log, not the screen.
        self::assertSame(JobLifecycle::NOT_QUEUED, $job->getError());
    }

    /**
     * The queue refused and the row cannot be marked either (the database is what
     * went): the caller still learns why the queue refused.
     */
    public function testAQueueRefusalReachesTheCallerEvenWhenTheRowCannotBeMarked(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $job->delete();

        $refusingQueue = new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw new \RuntimeException('The queue server is unreachable.');
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The queue server is unreachable.');

        $this->lifecycle($refusingQueue)->dispatch($job, new RunExportJob((int) $job->getId()));
    }

    /**
     * A job set aside before it was taken kept its row waiting: once its failure is
     * deleted, the row says so instead of waiting for a worker until the purge.
     */
    public function testARemovedFailureOfAJobStillWaitingMarksItsRow(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        self::assertSame(JobStatus::QUEUED, $job->getJobStatus());

        $this->getService(EventDispatcherInterface::class)->dispatch(new FailedJobRemovedEvent(new RunExportJob((int) $job->getId())));

        $job->reload();
        self::assertSame(JobStatus::FAILED, $job->getJobStatus());
        self::assertSame(RemovedJobRowListener::DELETED, $job->getError());
        self::assertNotNull($job->getFinishedAt());
    }

    /**
     * A worker that took the job meanwhile keeps it running: only a row still waiting
     * is marked.
     */
    public function testARemovedFailureLeavesAJobAWorkerTookRunning(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $job->setStatus(JobStatus::RUNNING->value)->save();

        $this->getService(EventDispatcherInterface::class)->dispatch(new FailedJobRemovedEvent(new RunExportJob((int) $job->getId())));

        $job->reload();
        self::assertSame(JobStatus::RUNNING, $job->getJobStatus());
    }

    /**
     * The images and documents added to an archive are not rows: the export still gives
     * a sign of life while it adds them, or a long archive would be taken for a dead job.
     */
    public function testAnExportStillGivesASignOfLifeWhileItAddsItsImages(): void
    {
        $export = $this->ordersExport();
        $export->setHandleClass(ImageHeavyExport::class)->save($this->getPropelConnection());
        ImageHeavyExport::$fileName = 'image-heavy-'.uniqid();
        $told = [];

        $event = $this->getService(ExportHandler::class)->export(
            $export,
            $this->getService(SerializerManager::class)->get(self::SERIALIZER),
            $this->archiverKeepingNothing(),
            Lang::getDefaultLanguage(),
            includeImages: true,
            onProgress: static function (int $rows) use (&$told): void {
                $told[] = $rows;
            },
        );

        (new Filesystem())->remove(glob(THELIA_CACHE_DIR.'export/*'.ImageHeavyExport::$fileName.'*') ?: []);
        // Once for the rows, then every DataTransferProgress::STEP images, with the same count.
        self::assertSame([2, 2, 2], $told);
    }

    /**
     * The rows are told once written, then again with the same count while the images
     * are added: the row is written all the same, so a long archive is never taken for
     * a job a dead worker left running.
     */
    public function testAJobAddingItsImagesStaysAlive(): void
    {
        $export = $this->ordersExport();
        $export->setHandleClass(ImageHeavyExport::class)->save($this->getPropelConnection());
        ImageHeavyExport::$fileName = 'image-heavy-'.uniqid();
        $archiver = $this->archiverKeepingNothing();
        $archivers = $this->getService(ArchiverManager::class);
        $archivers->add($archiver);
        $job = $this->launcherWith($this->queue())->launch($export, self::SERIALIZER, $archiver->getId(), Lang::getDefaultLanguage(), includeImages: true);
        $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        $added = 0;
        $lastSignOfLife = null;
        $archiver->onAdd = static function () use ($connection, $job, &$added, &$lastSignOfLife): void {
            ++$added;

            if (1 === $added) {
                $connection->exec(\sprintf('UPDATE export_job SET updated_at = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE id = %d', $job->getId()));
            }

            if (DataTransferProgress::STEP + 1 === $added) {
                $lastSignOfLife = $connection->query(\sprintf('SELECT updated_at FROM export_job WHERE id = %d', $job->getId()))->fetchColumn();
            }
        };

        try {
            ($this->handler())(new RunExportJob((int) $job->getId()));
        } finally {
            $archivers->remove($archiver->getId());
            (new Filesystem())->remove(glob(THELIA_CACHE_DIR.'export/*'.ImageHeavyExport::$fileName.'*') ?: []);
        }

        self::assertIsString($lastSignOfLife);
        self::assertGreaterThan((new \DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s'), $lastSignOfLife);
    }

    /**
     * The export is written and its row cannot record it: the file holds customer data
     * nobody will ever download, and goes with the failure.
     */
    public function testAnExportItsRowCannotRecordLeavesNoFileBehind(): void
    {
        $export = $this->ordersExport();
        $export->setHandleClass(ImageHeavyExport::class)->save($this->getPropelConnection());
        // Too long for the row once the folder is in front of it.
        ImageHeavyExport::$fileName = 'unrecorded-'.uniqid().'-'.str_repeat('x', 190);
        $job = $this->launcherWith($this->queue())->launch($export, self::SERIALIZER, language: Lang::getDefaultLanguage());

        try {
            ($this->handler())(new RunExportJob((int) $job->getId()));
            self::fail('The row cannot hold the path of the file.');
        } catch (JobSetAsideException) {
        }

        self::assertSame([], glob(THELIA_CACHE_DIR.'export/*'.ImageHeavyExport::$fileName.'*'));
    }

    /**
     * The file a listener of the export pointed elsewhere is not the export's to delete
     * when its row cannot record it: only a file of the export folder goes.
     */
    public function testARowThatCannotRecordAFileOutsideTheExportFolderLeavesItAlone(): void
    {
        $export = $this->ordersExport();
        $export->setHandleClass(ImageHeavyExport::class)->save($this->getPropelConnection());
        ImageHeavyExport::$fileName = 'image-heavy-'.uniqid();
        // Longer than the row can hold.
        $elsewhere = sys_get_temp_dir().'/'.uniqid('elsewhere-').'/'.str_repeat('d', 120).'/'.str_repeat('e', 120).'/kept.csv';
        (new Filesystem())->dumpFile($elsewhere, 'kept');
        $pointsElsewhere = static function (ExportEvent $event) use ($elsewhere): void {
            $event->setFilePath($elsewhere);
        };
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $dispatcher->addListener(TheliaEvents::EXPORT_SUCCESS, $pointsElsewhere);
        $job = $this->launcherWith($this->queue())->launch($export, self::SERIALIZER, language: Lang::getDefaultLanguage());

        try {
            ($this->handler())(new RunExportJob((int) $job->getId()));
            self::fail('The row cannot hold the path of the file.');
        } catch (JobSetAsideException) {
        } finally {
            $dispatcher->removeListener(TheliaEvents::EXPORT_SUCCESS, $pointsElsewhere);
            (new Filesystem())->remove(glob(THELIA_CACHE_DIR.'export/*'.ImageHeavyExport::$fileName.'*') ?: []);
        }

        self::assertFileExists($elsewhere);
        (new Filesystem())->remove(\dirname($elsewhere, 3));
    }

    /**
     * A worker runs export after export on the same handler: the rows of the previous
     * one are never told for the next, written by a module that tells none.
     */
    public function testAnExportNeverTellsTheRowsOfThePreviousOne(): void
    {
        $export = $this->ordersExport();
        $export->setHandleClass(ImageHeavyExport::class)->save($this->getPropelConnection());
        ImageHeavyExport::$fileName = 'image-heavy-'.uniqid();
        $handler = new ModuleWrittenExportHandler($this->getService(EventDispatcherInterface::class), $this->getService(ExportCachePurger::class));
        $serializer = $this->getService(SerializerManager::class)->get(self::SERIALIZER);
        $handler->export($export, $serializer, null, Lang::getDefaultLanguage(), onProgress: static function (): void {});
        $handler->writesItsOwnWay = true;
        $told = [];

        $handler->export(
            $export,
            $serializer,
            $this->archiverKeepingNothing(),
            Lang::getDefaultLanguage(),
            includeImages: true,
            onProgress: static function (int $rows) use (&$told): void {
                $told[] = $rows;
            },
        );

        (new Filesystem())->remove(glob(THELIA_CACHE_DIR.'export/*'.ImageHeavyExport::$fileName.'*') ?: []);
        self::assertSame([0, 0], $told);
    }

    /**
     * A file left half written holds customer data: it goes with the failure.
     */
    public function testAnExportThatBreaksHalfWayLeavesNoFileBehind(): void
    {
        $export = $this->ordersExport();
        $export->setHandleClass(ImageHeavyExport::class)->save($this->getPropelConnection());
        ImageHeavyExport::$fileName = 'image-heavy-'.uniqid();
        ImageHeavyExport::$breaksOnTheSecondRow = true;

        try {
            $this->getService(ExportHandler::class)->export($export, $this->getService(SerializerManager::class)->get(self::SERIALIZER), null, Lang::getDefaultLanguage());
            self::fail('The export breaks on its second row.');
        } catch (\RuntimeException) {
        } finally {
            ImageHeavyExport::$breaksOnTheSecondRow = false;
        }

        self::assertSame([], glob(THELIA_CACHE_DIR.'export/*'.ImageHeavyExport::$fileName.'*'));
    }

    /**
     * A zip is written when it is closed, and says so only by what save() returns: an
     * archive that was not written is a failed export, not a done one without its file.
     */
    public function testAnArchiveTheArchiverDidNotWriteFailsTheExport(): void
    {
        $export = $this->ordersExport();
        $export->setHandleClass(ImageHeavyExport::class)->save($this->getPropelConnection());
        ImageHeavyExport::$fileName = 'image-heavy-'.uniqid();
        $archiver = $this->archiverKeepingNothing();
        $archiver->saysItDidNotSave = true;

        try {
            $this->getService(ExportHandler::class)->export($export, $this->getService(SerializerManager::class)->get(self::SERIALIZER), $archiver, Lang::getDefaultLanguage());
            self::fail('An archive that was not written fails the export.');
        } catch (\RuntimeException) {
        }

        self::assertSame([], glob(THELIA_CACHE_DIR.'export/*'.ImageHeavyExport::$fileName.'*'));
    }

    /**
     * Once archived, the export holds customer data twice: only the archive is kept.
     */
    public function testAnArchivedExportKeepsOnlyItsArchive(): void
    {
        $export = $this->ordersExport();
        $export->setHandleClass(ImageHeavyExport::class)->save($this->getPropelConnection());
        ImageHeavyExport::$fileName = 'image-heavy-'.uniqid();

        $event = $this->getService(ExportHandler::class)->export($export, $this->getService(SerializerManager::class)->get(self::SERIALIZER), $this->archiverKeepingNothing(), Lang::getDefaultLanguage());
        $left = glob(THELIA_CACHE_DIR.'export/*'.ImageHeavyExport::$fileName.'*') ?: [];
        (new Filesystem())->remove($left);

        self::assertSame([$event->getFilePath()], $left);
    }

    public function testAnArchiveThatCannotBeWrittenLeavesNoFileBehind(): void
    {
        $export = $this->ordersExport();
        $export->setHandleClass(ImageHeavyExport::class)->save($this->getPropelConnection());
        ImageHeavyExport::$fileName = 'image-heavy-'.uniqid();
        $archiver = $this->archiverKeepingNothing();
        $archiver->refusesToSave = true;

        try {
            $this->getService(ExportHandler::class)->export($export, $this->getService(SerializerManager::class)->get(self::SERIALIZER), $archiver, Lang::getDefaultLanguage());
            self::fail('The archive cannot be written.');
        } catch (\RuntimeException) {
        }

        self::assertSame([], glob(THELIA_CACHE_DIR.'export/*'.ImageHeavyExport::$fileName.'*'));
    }

    /**
     * A zip still open writes the files it holds when it is let go: a failed archive
     * must not come back once its files are removed.
     */
    public function testAZipThatFailsHalfWayDoesNotComeBack(): void
    {
        $export = $this->ordersExport();
        $export->setHandleClass(ImageHeavyExport::class)->save($this->getPropelConnection());
        ImageHeavyExport::$fileName = 'image-heavy-'.uniqid();
        $image = THELIA_CACHE_DIR.'export-test-image-'.uniqid().'.png';
        file_put_contents($image, 'png');
        ImageHeavyExport::$paths = [$image, '/nowhere/missing.png'];
        $archiver = $this->getService(ArchiverManager::class)->get('thelia.zip');

        try {
            $this->getService(ExportHandler::class)->export($export, $this->getService(SerializerManager::class)->get(self::SERIALIZER), $archiver, Lang::getDefaultLanguage(), includeImages: true);
            self::fail('An image of the export is missing.');
        } catch (\RuntimeException) {
        } finally {
            ImageHeavyExport::$paths = null;
        }

        // The zip of the archiver service is let go when it is replaced or the process
        // ends: it would then write the image it holds, still on disk.
        $archiver->create(THELIA_CACHE_DIR.'export-test-other-'.uniqid());
        $archiver->close();
        array_map('unlink', [$image, ...(glob(THELIA_CACHE_DIR.'export-test-other-*') ?: [])]);

        self::assertSame([], glob(THELIA_CACHE_DIR.'export/*'.ImageHeavyExport::$fileName.'*'));
    }

    /**
     * The last count of the rows is told once the file is written: a caller that fails
     * then (its row cannot be saved) leaves no file behind either.
     */
    public function testAnExportWhoseLastProgressFailsLeavesNoFileBehind(): void
    {
        $export = $this->ordersExport();
        $export->setHandleClass(ImageHeavyExport::class)->save($this->getPropelConnection());
        ImageHeavyExport::$fileName = 'image-heavy-'.uniqid();

        try {
            $this->getService(ExportHandler::class)->export(
                $export,
                $this->getService(SerializerManager::class)->get(self::SERIALIZER),
                null,
                Lang::getDefaultLanguage(),
                onProgress: static function (): void {
                    throw new \RuntimeException('The row of the job cannot be saved.');
                },
            );
            self::fail('The progress cannot be recorded.');
        } catch (\RuntimeException) {
        }

        self::assertSame([], glob(THELIA_CACHE_DIR.'export/*'.ImageHeavyExport::$fileName.'*'));
    }

    /**
     * A listener of the export that fails still leaves no file behind.
     */
    public function testAnExportWhoseListenerFailsLeavesNoFileBehind(): void
    {
        $export = $this->ordersExport();
        $export->setHandleClass(ImageHeavyExport::class)->save($this->getPropelConnection());
        ImageHeavyExport::$fileName = 'image-heavy-'.uniqid();
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $failing = static function (): void {
            throw new \RuntimeException('A listener of the export failed.');
        };
        $dispatcher->addListener(TheliaEvents::EXPORT_SUCCESS, $failing);

        try {
            $this->getService(ExportHandler::class)->export($export, $this->getService(SerializerManager::class)->get(self::SERIALIZER), null, Lang::getDefaultLanguage());
            self::fail('The listener fails.');
        } catch (\RuntimeException) {
        } finally {
            $dispatcher->removeListener(TheliaEvents::EXPORT_SUCCESS, $failing);
        }

        self::assertSame([], glob(THELIA_CACHE_DIR.'export/*'.ImageHeavyExport::$fileName.'*'));
    }

    /**
     * The failure transport keeps what the job says of its failure, and the back
     * office lists it: never the text of a database error.
     */
    public function testAFailureSetAsideNeverQuotesADatabaseError(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());

        try {
            $this->lifecycle($this->queue())->fail(new RunExportJob((int) $job->getId()), $job, new \PDOException("SQLSTATE[23000]: Duplicate entry 'buyer@example.com'"));
        } catch (UnrecoverableMessageHandlingException $exception) {
            self::assertStringNotContainsString('buyer@example.com', $exception->getMessage());
            self::assertStringContainsString(JobFailureMessage::SERVER_ERROR, $exception->getMessage());
            // The failure transport stores the whole chain: the cause is not in it.
            self::assertNull($exception->getPrevious());
        }

        $job->reload();
        self::assertSame(JobFailureMessage::SERVER_ERROR, $job->getError());
    }

    /**
     * Without a queue, a job that fails before it is even taken (its row unreadable,
     * the claim refused by the database) is not left waiting for nobody.
     */
    public function testWithoutAQueueAJobThatFailsBeforeItStartsIsRecordedAsFailed(): void
    {
        $failingAtOnce = new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw new HandlerFailedException(Envelope::wrap($message), [new \PDOException('SQLSTATE[HY000] [2002] Connection refused')]);
            }
        };

        $job = $this->launcherWith($failingAtOnce)->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());

        self::assertSame(JobStatus::FAILED, $job->getJobStatus());
        self::assertSame(JobFailureMessage::SERVER_ERROR, $job->getError());
    }

    /**
     * Without a queue, the job failed before it started and its row cannot be read
     * again either (the database is what went): the failure is logged, and what the
     * caller is told comes from the row, never from a database error.
     */
    public function testWithoutAQueueAJobThatFailsBeforeItStartsLeavesAnUnreadableRowAlone(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $job->delete();
        $failingAtOnce = new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw new HandlerFailedException(Envelope::wrap($message), [new \PDOException('SQLSTATE[HY000] [2002] Connection refused')]);
            }
        };

        $this->lifecycle($failingAtOnce)->dispatch($job, new RunExportJob((int) $job->getId()));

        self::assertNull(ExportJobQuery::create()->findPk($job->getId()));
    }

    /**
     * Taking the job may fail before the job runs (the database gone, the queue
     * refusing the message that looks again): what is set aside then says no more
     * than any other failure, and the row is left to the run that holds it.
     */
    public function testAFailureBeforeTheJobRunsNeverQuotesItsCause(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $job->setStatus(JobStatus::RUNNING->value)->save($this->getPropelConnection());
        $refusing = new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw new \PDOException("SQLSTATE[23000]: Duplicate entry 'buyer@example.com'");
            }
        };

        try {
            $this->handlerWith($refusing)(new RunExportJob($job->getId()));
            self::fail('The message must be set aside.');
        } catch (UnrecoverableMessageHandlingException $exception) {
            self::assertStringContainsString(JobFailureMessage::SERVER_ERROR, $exception->getMessage());
            self::assertStringNotContainsString('buyer@example.com', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }

        $job->reload();
        self::assertSame(JobStatus::RUNNING, $job->getJobStatus());
    }

    /**
     * The format may have gone with its module since the export was asked for.
     */
    public function testAnExportWhoseFormatIsGoneSaysSo(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $job->setSerializer('thelia.gone')->save($this->getPropelConnection());

        try {
            $this->handler()(new RunExportJob($job->getId()));
        } catch (UnrecoverableMessageHandlingException) {
        }

        $job->reload();
        self::assertSame('The format "thelia.gone" is no longer available on this server.', $job->getError());
    }

    /**
     * messenger:failed:retry hands the bus the envelope it read from the failure
     * transport: through the real bus, the job looked at again three times restarts.
     */
    public function testAJobReplayedFromTheFailureTransportRestartsThroughTheBus(): void
    {
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $job->setStatus(JobStatus::FAILED->value)->save($this->getPropelConnection());

        $this->getService(MessageBusInterface::class)->dispatch(new Envelope(new RunExportJob($job->getId(), 3), [
            new SentToFailureTransportStamp('async_heavy'),
            new ReceivedStamp('failed'),
        ]));
        $job->reload();
        $this->files[] = (string) $job->getFilePath();

        self::assertSame(JobStatus::DONE, $job->getJobStatus());
    }

    /**
     * Anything but a year of four digits and a month of the year is refused, rather
     * than read as another date or as no bound at all.
     */
    public function testDatesTheExportFormCannotHaveSentAreRefused(): void
    {
        foreach ([['year' => '2026; DROP', 'month' => '1'], ['year' => '99999', 'month' => '1'], ['year' => '26', 'month' => '1'], ['year' => '2026', 'month' => '13'], ['year' => '2026', 'month' => '0']] as $bound) {
            try {
                $this->getService(ExportHandler::class)->resolveRangeDate(['start' => $bound, 'end' => null]);
                self::fail('Refused: '.json_encode($bound, \JSON_THROW_ON_ERROR));
            } catch (JobRefusedException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAnUnknownSerializerIsRefusedBeforeAnyJobIsRecorded(): void
    {
        $before = ExportJobQuery::create()->count();

        try {
            $this->getService(ExportJobLauncher::class)->launch($this->ordersExport(), 'thelia.nothing');
            self::fail('An unknown serializer must be refused in the request.');
        } catch (\Throwable) {
        }

        self::assertSame($before, ExportJobQuery::create()->count());
    }

    /**
     * @return ArchiverInterface&object{refusesToSave: bool, saysItDidNotSave: bool, onAdd: ?\Closure}
     */
    private function archiverKeepingNothing(): ArchiverInterface
    {
        return new class implements ArchiverInterface {
            public bool $refusesToSave = false;

            public bool $saysItDidNotSave = false;

            /** @var (\Closure(): void)|null told of every file added */
            public ?\Closure $onAdd = null;
            private string $archivePath = '';

            public function getId(): string
            {
                return 'nothing';
            }

            public function getName(): string
            {
                return 'Nothing';
            }

            public function getExtension(): string
            {
                return 'nothing';
            }

            public function getMimeType(): string
            {
                return 'application/octet-stream';
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function getArchivePath(): string
            {
                return $this->archivePath;
            }

            public function setArchivePath(string $archivePath): self
            {
                $this->archivePath = $archivePath;

                return $this;
            }

            public function create(string $baseName): self
            {
                $this->archivePath = $baseName.'.nothing';
                touch($this->archivePath);

                return $this;
            }

            public function open(string $path): self
            {
                return $this;
            }

            public function add(string $path, ?string $pathInArchive = null): self
            {
                if (null !== $this->onAdd) {
                    ($this->onAdd)();
                }

                return $this;
            }

            public function save(): bool
            {
                if ($this->refusesToSave) {
                    throw new \RuntimeException('The archive cannot be written.');
                }

                return !$this->saysItDidNotSave;
            }

            public function extract(string $toPath): void
            {
            }
        };
    }

    private function ordersExport(): Export
    {
        $export = ExportQuery::create()->findOneByRef('thelia.export.orders');

        if (null === $export) {
            self::markTestSkipped('The test database has no order export.');
        }

        return $export;
    }

    private function launcherWith(MessageBusInterface $bus): ExportJobLauncher
    {
        return new ExportJobLauncher(
            $this->getService(ExportHandler::class),
            $this->getService(SerializerManager::class),
            $this->getService(ArchiverManager::class),
            $this->lifecycle($bus),
        );
    }

    private function handler(): RunExportJobHandler
    {
        return $this->getService(RunExportJobHandler::class);
    }

    private function handlerWith(MessageBusInterface $bus): RunExportJobHandler
    {
        return new RunExportJobHandler(
            $this->getService(ExportHandler::class),
            $this->getService(SerializerManager::class),
            $this->getService(ArchiverManager::class),
            $this->lifecycle($bus),
        );
    }

    private function lifecycle(MessageBusInterface $bus, string $heavyDsn = 'doctrine://default?queue_name=heavy'): JobLifecycle
    {
        return new JobLifecycle(new JobClaim(), $bus, new ConfiguredQueues($this->getService(ShopDatabaseConnection::class), 'doctrine://default', $heavyDsn, 'doctrine://default?queue_name=failed'));
    }

    /**
     * @return MessageBusInterface&object{kept: list<object>, stamps: list<array<mixed>>}
     */
    private function queue(): MessageBusInterface
    {
        return new class implements MessageBusInterface {
            /** @var list<object> */
            public array $kept = [];

            /** @var list<array<mixed>> */
            public array $stamps = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->kept[] = $message;
                $this->stamps[] = $stamps;

                return Envelope::wrap($message, $stamps);
            }
        };
    }
}
