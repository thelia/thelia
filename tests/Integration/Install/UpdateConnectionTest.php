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

use Propel\Runtime\Propel;
use Thelia\Core\Install\Database;
use Thelia\Core\Install\Update;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The update loop runs on the connection Propel opened, and Propel opens a
 * PdoConnection: it speaks the same query(), prepare() and exec() as \PDO, but it
 * does not extend it and keeps its own \PDO private.
 *
 * Everything the loop does with that connection is exercised here, because both
 * places that assumed a \PDO failed silently or late: the handle given to the PHP
 * update scripts, and the write of the version marker that records how far the
 * update went.
 */
final class UpdateConnectionTest extends IntegrationTestCase
{
    public function testAnUpdateScriptGetsAUsableConnection(): void
    {
        $connection = $this->database()->getConnection();

        // What setup/update/php/3.0.0-beta3.php does on its first line.
        $version = $connection
            ->query("SELECT `value` FROM `config` WHERE `name` = 'thelia_version'")
            ->fetchColumn();

        self::assertIsString($version);
    }

    public function testAnUpdateScriptCanWriteThroughAPreparedStatement(): void
    {
        $connection = $this->database()->getConnection();

        $statement = $connection->prepare('UPDATE `config` SET `value` = :value WHERE `name` = :name');
        $statement->execute(['value' => 'written by an update script', 'name' => 'thelia_version']);

        self::assertSame('written by an update script', $this->readVersionMarker());
    }

    public function testTheVersionMarkerIsWritten(): void
    {
        $update = new Update(false);

        $update->setCurrentVersion('3.0.0-marker');

        self::assertSame('3.0.0-marker', $this->readVersionMarker());
        self::assertSame('3.0.0-marker', $update->getCurrentVersion());
    }

    private function database(): Database
    {
        return new Database(Propel::getConnection(ProductTableMap::DATABASE_NAME));
    }

    private function readVersionMarker(): string
    {
        return (string) Propel::getConnection(ProductTableMap::DATABASE_NAME)
            ->query("SELECT `value` FROM `config` WHERE `name` = 'thelia_version'")
            ->fetchColumn();
    }
}
