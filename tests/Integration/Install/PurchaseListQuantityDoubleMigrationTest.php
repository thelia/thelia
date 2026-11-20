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
use Thelia\Model\Map\CustomerListItemTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The statements 3.5.0.sql runs to let a purchase list keep 1.25 kg.
 *
 * They run on a scratch table of the same shape as `customer_list_item`: a schema change
 * commits on its own, and the real table stays as the fresh install made it.
 */
final class PurchaseListQuantityDoubleMigrationTest extends IntegrationTestCase
{
    private const PROBE = 'list_quantity_to_double_probe';

    private const SCRIPT = '3.5.0.sql';

    protected bool $useTransaction = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection()->exec('DROP TABLE IF EXISTS `'.self::PROBE.'`');
    }

    protected function tearDown(): void
    {
        $this->connection()->exec('DROP TABLE IF EXISTS `'.self::PROBE.'`');

        parent::tearDown();
    }

    public function testAListSavedInWholeUnitsKeepsItsQuantitiesAndTakesDecimalsAfterwards(): void
    {
        $this->createProbe('INTEGER NOT NULL');
        $this->connection()->exec('INSERT INTO `'.self::PROBE.'` (`quantity`) VALUES (3), (12), (999999)');

        $this->runMigration();
        $this->connection()->exec('INSERT INTO `'.self::PROBE.'` (`quantity`) VALUES (1.25)');

        self::assertSame(['double', 'NO'], $this->columnDefinition());
        self::assertSame(['3', '12', '999999', '1.25'], $this->quantities());
    }

    public function testTheScriptCanBeReplayed(): void
    {
        $this->createProbe('DOUBLE NOT NULL');
        $this->connection()->exec('INSERT INTO `'.self::PROBE.'` (`quantity`) VALUES (1.25), (0.005)');

        $this->runMigration();
        $this->runMigration();

        self::assertSame(['1.25', '0.005'], $this->quantities());
    }

    public function testTheFreshInstallStoresTheQuantityOfAListLineInDoublePrecision(): void
    {
        $type = $this->connection()
            ->query("SELECT `DATA_TYPE` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer_list_item' AND `COLUMN_NAME` = 'quantity'")
            ->fetchColumn();

        self::assertSame('double', $type);
    }

    private function createProbe(string $definition): void
    {
        $this->connection()->exec('CREATE TABLE `'.self::PROBE.'` (`id` INTEGER NOT NULL AUTO_INCREMENT, `quantity` '.$definition.', PRIMARY KEY (`id`)) ENGINE=InnoDB');
    }

    private function runMigration(): void
    {
        foreach ($this->migrationStatements() as $statement) {
            $this->connection()->exec($statement);
        }
    }

    /**
     * The statements of the script that convert `customer_list_item`.`quantity`, aimed at the probe.
     *
     * @return list<string>
     */
    private function migrationStatements(): array
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.self::SCRIPT);

        $statements = [];

        foreach (explode(";\n", $script) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (str_contains($sql, 'list_quantity_to_double')) {
                $statements[] = str_replace(['`customer_list_item`', "'customer_list_item'"], ['`'.self::PROBE.'`', "'".self::PROBE."'"], $sql);
            }
        }

        self::assertCount(5, $statements, \sprintf('%s does not convert the quantity of a purchase list line.', self::SCRIPT));

        return $statements;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function columnDefinition(): array
    {
        $row = $this->connection()
            ->query("SELECT `DATA_TYPE`, `IS_NULLABLE` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '".self::PROBE."' AND `COLUMN_NAME` = 'quantity'")
            ->fetch(\PDO::FETCH_NUM);

        self::assertIsArray($row);

        return [(string) $row[0], (string) $row[1]];
    }

    /**
     * @return list<string>
     */
    private function quantities(): array
    {
        $rows = $this->connection()->query('SELECT `quantity` FROM `'.self::PROBE.'` ORDER BY `id`')->fetchAll(\PDO::FETCH_COLUMN);

        return array_map(static fn (mixed $quantity): string => (string) $quantity, $rows);
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getWriteConnection(CustomerListItemTableMap::DATABASE_NAME);
    }
}
