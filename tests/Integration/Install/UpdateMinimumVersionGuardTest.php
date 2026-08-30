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
use Thelia\Core\Install\Exception\UpdateException;
use Thelia\Core\Install\Update;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The updater must refuse a Thelia 2 database instead of quietly wrecking it. Its
 * scripts jump from 2.5.5 to 3.0.0-alpha1, so a 2.6 database would be taken for
 * 2.5.5 and have the 3.0 migrations replayed over a schema they never matched, while
 * the version marker was rewritten to 3.0. The guard stops that before the first
 * script runs, and leaves the marker untouched so nothing is half-migrated.
 */
final class UpdateMinimumVersionGuardTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    private string $initialVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initialVersion = $this->readVersionMarker();
    }

    protected function tearDown(): void
    {
        $this->writeVersionMarker($this->initialVersion);

        parent::tearDown();
    }

    public function testAThelia2DatabaseIsRefusedAndLeftUntouched(): void
    {
        $this->writeVersionMarker('2.6.2');

        try {
            (new Update(false))->process();
            self::fail('An update from a Thelia 2 database must be refused.');
        } catch (UpdateException $exception) {
            self::assertStringContainsString('cannot update a Thelia 2 database in place', $exception->getMessage());
        }

        // The guard runs before any script, so the marker is exactly what it was:
        // the database was not moved a single version forward.
        self::assertSame('2.6.2', $this->readVersionMarker());
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
