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
use Thelia\Core\Install\Exception\UpdateException;
use Thelia\Core\Install\Update;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * What the update loop reports has to match what it did, because update.php offers to
 * restore the backup on a failure, and that restore rewrites every table.
 *
 * Two things made it lie. The loop ran inside a transaction, and MariaDB commits
 * implicitly on every CREATE, ALTER and DROP, so the transaction ended at the first
 * schema change and the commit closing the loop raised "There is no active
 * transaction" on a migration that had succeeded. And a failing statement arrives as a
 * PDOException whose code is the SQLSTATE string, which Exception refuses, so wrapping
 * a real failure raised a TypeError and never reached update.php at all.
 *
 * The loop is driven here by steps written for the test. The shipped scripts are no
 * use for this: their statements are guarded and skip the DDL on a database that
 * already carries it.
 */
final class UpdateTransactionTest extends IntegrationTestCase
{
    private const PROBE_DDL = 'CREATE TABLE IF NOT EXISTS `update_transaction_probe` (`id` INTEGER) ENGINE=InnoDB';

    protected bool $useTransaction = false;

    private string $initialVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initialVersion = $this->readVersionMarker();
    }

    protected function tearDown(): void
    {
        $this->connection()->exec('DROP TABLE IF EXISTS `update_transaction_probe`');
        $this->writeVersionMarker($this->initialVersion);

        parent::tearDown();
    }

    public function testARunCrossingASchemaChangeIsNotReportedAsAFailure(): void
    {
        $update = $this->updateRunning(
            static fn (string $version, Database $database) => $database->execute(self::PROBE_DDL),
        );
        $this->startAt($this->nthFromLast(1));

        $applied = $update->process();

        self::assertSame([$update->getLatestVersion()], $applied);
        self::assertSame($update->getLatestVersion(), $this->readVersionMarker());
    }

    public function testAVersionThatWentThroughKeepsItsMarkerWhenALaterOneFails(): void
    {
        $lastVersion = $this->nthFromLast(0);
        $versionBefore = $this->nthFromLast(1);

        // Data only, and the failure comes before any schema change: this is the case
        // where a transaction was still open when the loop gave up. Rolling back there
        // erased the marker of the version that had already been applied, so the next
        // run replayed it from the start.
        $update = $this->updateRunning(
            static function (string $version) use ($lastVersion): void {
                if ($version === $lastVersion) {
                    throw new \RuntimeException('the update script blew up');
                }
            },
        );
        $this->startAt($this->nthFromLast(2));

        try {
            $update->process();
            self::fail('A failing update script must be reported.');
        } catch (UpdateException) {
        }

        self::assertSame($versionBefore, $this->readVersionMarker());
    }

    public function testASqlErrorIsReportedAsAnUpdateFailure(): void
    {
        $update = $this->updateRunning(
            static fn (string $version, Database $database) => $database->execute('SELECT * FROM `a_table_that_does_not_exist`'),
        );
        $this->startAt($this->nthFromLast(1));

        try {
            $update->process();
            self::fail('A failing update script must be reported.');
        } catch (UpdateException $exception) {
            // Reported, rather than raising a TypeError on the way out, so that
            // update.php can print it and offer the backup it took beforehand.
            self::assertStringContainsString('a_table_that_does_not_exist', $exception->getMessage());
            self::assertSame($this->nthFromLast(0), $exception->getVersion());
        }
    }

    private function updateRunning(\Closure $step): Update
    {
        return new class(false, $step) extends Update {
            public function __construct(bool $usePropel, private readonly \Closure $step)
            {
                parent::__construct($usePropel);
            }

            protected function updateToVersion(string $version, Database $database): void
            {
                ($this->step)($version, $database);

                $this->setCurrentVersion($version);
            }
        };
    }

    /**
     * The version $offset places before the last one on the list the loop walks.
     */
    private function nthFromLast(int $offset): string
    {
        $versions = (new Update(false))->getVersions();

        return $versions[\count($versions) - 1 - $offset];
    }

    private function startAt(string $version): void
    {
        $this->writeVersionMarker($version);
    }

    private function readVersionMarker(): string
    {
        return (string) $this->connection()
            ->query("SELECT `value` FROM `config` WHERE `name` = 'thelia_version'")
            ->fetchColumn();
    }

    private function writeVersionMarker(string $version): void
    {
        $statement = $this->connection()->prepare("UPDATE `config` SET `value` = ? WHERE `name` = 'thelia_version'");
        $statement->execute([$version]);
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getConnection(ProductTableMap::DATABASE_NAME);
    }
}
