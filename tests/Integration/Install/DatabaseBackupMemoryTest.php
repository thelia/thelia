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

namespace Thelia\Tests\Integration\Install;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Core\Install\Database;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The backup used to build the whole dump in memory before writing a byte of it, on
 * top of a result set the driver had already buffered in full. A 2.8 GB database took
 * the process past 7 GB and the system killed it. The dump is now written as it is
 * read, so what it takes in memory must not depend on how much there is to dump.
 */
final class DatabaseBackupMemoryTest extends IntegrationTestCase
{
    private const TABLE = 'backup_memory_bound';

    /** 4096 rows of 8 KiB. */
    private const DATA_BYTES = 32 * 1024 * 1024;

    /** A quarter of the data: the old dump needed several times the data, a streamed one a few batches. */
    private const MEMORY_CEILING_BYTES = 8 * 1024 * 1024;

    protected bool $useTransaction = false;

    private string $dumpFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dumpFile = tempnam(sys_get_temp_dir(), 'thelia-backup-').'.sql';
        $this->connection()->exec('DROP TABLE IF EXISTS `'.self::TABLE.'`');
        $this->connection()->exec(
            'CREATE TABLE `'.self::TABLE.'` (
                `id` INTEGER NOT NULL AUTO_INCREMENT,
                `payload` LONGTEXT NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB CHARACTER SET=\'utf8mb4\' COLLATE=\'utf8mb4_general_ci\'',
        );
    }

    protected function tearDown(): void
    {
        $this->connection()->exec('DROP TABLE IF EXISTS `'.self::TABLE.'`');
        @unlink($this->dumpFile);

        parent::tearDown();
    }

    public function testTheMemoryABackupTakesDoesNotGrowWithTheData(): void
    {
        $this->fill(self::DATA_BYTES);

        $before = memory_get_usage();
        memory_reset_peak_usage();

        (new Database($this->connection()))->backupDb($this->dumpFile, [self::TABLE]);

        $peak = memory_get_peak_usage() - $before;

        self::assertGreaterThan(self::DATA_BYTES, filesize($this->dumpFile), 'The dump does not hold the data it was meant to.');
        self::assertLessThan(
            self::MEMORY_CEILING_BYTES,
            $peak,
            \sprintf('Backing up %d MiB of rows took %.1f MiB of memory.', self::DATA_BYTES / 1024 ** 2, $peak / 1024 ** 2),
        );
    }

    public function testADumpCutIntoSeveralInsertsRestoresEveryRow(): void
    {
        // Several batches' worth, so the restore replays more than one INSERT.
        $this->fill(4 * 1024 * 1024);
        $before = $this->fingerprint();

        $database = new Database($this->connection());
        $database->backupDb($this->dumpFile, [self::TABLE]);

        self::assertGreaterThan(1, substr_count((string) file_get_contents($this->dumpFile), 'INSERT INTO `'.self::TABLE.'`'));

        $database->restoreDb($this->dumpFile);

        self::assertSame($before, $this->fingerprint());
    }

    public function testTheConnectionStillBuffersItsReadsAfterABackup(): void
    {
        $this->fill(8 * 1024);

        (new Database($this->connection()))->backupDb($this->dumpFile, [self::TABLE]);

        // An unbuffered read left behind would make this second query fail while the
        // first one still has rows to hand out.
        $first = $this->connection()->query('SELECT `id` FROM `'.self::TABLE.'`');
        $first->fetch();
        $count = $this->connection()->query('SELECT COUNT(*) FROM `'.self::TABLE.'`')->fetchColumn();

        self::assertSame(1, (int) $count);
    }

    /**
     * 8 KiB rows, doubled until the table holds $bytes.
     */
    private function fill(int $bytes): void
    {
        $rowBytes = 8 * 1024;
        $this->connection()->exec(
            'INSERT INTO `'.self::TABLE."` (`payload`) VALUES (REPEAT('l''apostrophe \\\\ et 🛒 abcdefghi', ".intdiv($rowBytes, 32).'))',
        );

        for ($rows = 1; $rows * $rowBytes < $bytes; $rows *= 2) {
            $this->connection()->exec('INSERT INTO `'.self::TABLE.'` (`payload`) SELECT `payload` FROM `'.self::TABLE.'`');
        }
    }

    /**
     * @return array{string, string}
     */
    private function fingerprint(): array
    {
        $row = $this->connection()
            ->query('SELECT COUNT(*), SUM(CRC32(CONCAT(`id`, `payload`))) FROM `'.self::TABLE.'`')
            ->fetch(\PDO::FETCH_NUM);

        return [(string) $row[0], (string) $row[1]];
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getConnection(ProductTableMap::DATABASE_NAME);
    }
}
