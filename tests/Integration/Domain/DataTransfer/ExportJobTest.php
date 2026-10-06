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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Thelia\Core\Archiver\ArchiverManager;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Domain\DataTransfer\ExportHandler;
use Thelia\Domain\DataTransfer\Job\ExportJobLauncher;
use Thelia\Domain\DataTransfer\Job\JobClaim;
use Thelia\Domain\DataTransfer\Job\JobLifecycle;
use Thelia\Domain\DataTransfer\Job\JobStatus;
use Thelia\Domain\DataTransfer\Job\RunExportJob;
use Thelia\Domain\DataTransfer\Job\RunExportJobHandler;
use Thelia\Model\Export;
use Thelia\Model\ExportJobQuery;
use Thelia\Model\ExportQuery;
use Thelia\Model\Lang;
use Thelia\Test\IntegrationTestCase;

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

    public function testAJobStillRunningAfterEveryCheckIsLeftAlone(): void
    {
        $queue = $this->queue();
        $job = $this->launcherWith($this->queue())->launch($this->ordersExport(), self::SERIALIZER, language: Lang::getDefaultLanguage());
        $job->setStatus(JobStatus::RUNNING->value)->save($this->getPropelConnection());

        $this->handlerWith($queue)(new RunExportJob($job->getId(), JobLifecycle::MAX_POSTPONEMENTS));

        self::assertCount(0, $queue->kept);
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
            new JobLifecycle(new JobClaim(), $bus, 'sync://'),
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
        self::assertSame('The job could not be queued. The details are in the server log.', $job->getError());
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
            self::lifecycle($bus),
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
            self::lifecycle($bus),
        );
    }

    private static function lifecycle(MessageBusInterface $bus): JobLifecycle
    {
        return new JobLifecycle(new JobClaim(), $bus, 'doctrine://default?queue_name=heavy');
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
