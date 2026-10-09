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
use Thelia\Domain\DataTransfer\Job\JobHeartbeat;
use Thelia\Domain\DataTransfer\Job\JobStatus;
use Thelia\Messenger\Transport\ShopDatabaseConnection;
use Thelia\Model\ImportJob;
use Thelia\Model\ImportQuery;
use Thelia\Model\Map\ImportJobTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The sign of life of an import is seen by the other workers while the import still
 * writes inside its own transaction. Played without the test transaction, and the
 * row is deleted afterwards.
 */
final class JobHeartbeatTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    private ImportJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->job = (new ImportJob())
            ->setImportId((int) ImportQuery::create()->findOne()?->getId())
            ->setStatus(JobStatus::RUNNING->value)
            ->setFilePath('/nowhere.csv')
            ->setFileName('stock.csv')
            ->setUpdatedAt(new \DateTime('-2 hours'));
        $this->job->save();
    }

    protected function tearDown(): void
    {
        $this->job->delete();

        parent::tearDown();
    }

    public function testASignOfLifeIsSeenWhileTheImportIsStillInItsTransaction(): void
    {
        $connection = Propel::getWriteConnection(ImportJobTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->getService(JobHeartbeat::class)->beat(ImportJobTableMap::TABLE_NAME, (int) $this->job->getId());

            $seenByAnotherWorker = $this->getService(ShopDatabaseConnection::class)->get()
                ->fetchOne('SELECT updated_at FROM import_job WHERE id = ?', [$this->job->getId()]);
        } finally {
            $connection->rollBack();
        }

        self::assertGreaterThan(new \DateTimeImmutable('-1 minute'), new \DateTimeImmutable((string) $seenByAnotherWorker));
    }

    /**
     * The table is written into the SQL: only the tables of the jobs are ever named.
     */
    public function testABeatNamesOnlyAJobTable(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->getService(JobHeartbeat::class)->beat('customer', 1);
    }
}
