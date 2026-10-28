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
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The statements 3.4.0.sql runs to store the quantities in double precision.
 *
 * A FLOAT turned into a DOUBLE keeps its binary error (0.7 becomes 0.699999988079071), so the
 * script brings each converted value back to what was written. The case runs the statements of
 * `cart_item` on a scratch table of the same shape: a schema change commits on its own, and the
 * real table stays as the fresh install made it.
 */
final class QuantityDoublePrecisionMigrationTest extends IntegrationTestCase
{
    private const PROBE = 'quantity_to_double_probe';

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

    public function testAFloatQuantityBecomesTheDoubleThatWasWritten(): void
    {
        $this->createProbe('FLOAT DEFAULT 1');
        $this->connection()->exec('INSERT INTO `'.self::PROBE.'` (`quantity`) VALUES (0.7), (1234.567), (123456.7), (0), (NULL)');

        $this->runMigration();

        self::assertSame(['double', 'YES', '1'], $this->columnDefinition());
        self::assertSame(['0.7', '1234.567', '123456.7', '0', null], $this->quantities());
    }

    public function testAColumnAlreadyInDoubleIsLeftAlone(): void
    {
        $this->createProbe('DOUBLE DEFAULT 1');
        $this->connection()->exec('INSERT INTO `'.self::PROBE.'` (`quantity`) VALUES (0.0005), (1234.5678)');

        $this->runMigration();
        $this->runMigration();

        self::assertSame(['0.0005', '1234.5678'], $this->quantities());
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
     * The statements of the script that convert `cart_item`.`quantity`, aimed at the probe.
     *
     * @return list<string>
     */
    private function migrationStatements(): array
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.4.0.sql');

        $statements = [];
        $table = null;

        foreach (explode(";\n", $script) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (!str_contains($sql, 'quantity_to_double')) {
                continue;
            }

            if (1 === preg_match("/`TABLE_NAME` = '(\\w+)'/", $sql, $match)) {
                $table = $match[1];
            }

            if ('cart_item' === $table) {
                $statements[] = str_replace(['`cart_item`', "'cart_item'"], ['`'.self::PROBE.'`', "'".self::PROBE."'"], $sql);
            }
        }

        self::assertCount(9, $statements, 'The 3.4.0 script does not convert the quantity of a cart line.');

        return $statements;
    }

    /**
     * @return array{0: string, 1: string, 2: string|null}
     */
    private function columnDefinition(): array
    {
        $row = $this->connection()
            ->query("SELECT `DATA_TYPE`, `IS_NULLABLE`, `COLUMN_DEFAULT` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '".self::PROBE."' AND `COLUMN_NAME` = 'quantity'")
            ->fetch(\PDO::FETCH_NUM);

        self::assertIsArray($row);

        return [(string) $row[0], (string) $row[1], null === $row[2] ? null : (string) $row[2]];
    }

    /**
     * @return list<string|null>
     */
    private function quantities(): array
    {
        $rows = $this->connection()->query('SELECT `quantity` FROM `'.self::PROBE.'` ORDER BY `id`')->fetchAll(\PDO::FETCH_COLUMN);

        return array_map(static fn (mixed $quantity): ?string => null === $quantity ? null : (string) $quantity, $rows);
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME);
    }
}
