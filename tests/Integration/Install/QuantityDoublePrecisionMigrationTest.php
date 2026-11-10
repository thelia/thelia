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

    /**
     * Every column the script converts, read from the script, is a DOUBLE in the schema a
     * fresh install builds: the scratch table above proves the statements, not the schema.
     */
    public function testEveryColumnTheScriptConvertsIsADoubleInTheSchema(): void
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.4.0.sql');
        preg_match_all('/ALTER TABLE `(\w+)` MODIFY `(\w+)` DOUBLE/', $script, $matches, \PREG_SET_ORDER);
        $converted = array_values(array_unique(array_map(static fn (array $match): string => $match[1].'.'.$match[2], $matches)));
        sort($converted);

        self::assertSame([
            'cart_item.quantity',
            'cart_item.reserved_quantity',
            'order_product.quantity',
            'order_return_line.quantity',
            'order_return_line.quantity_received',
            'order_shipment_item.quantity',
            'product_sale_elements.low_stock_threshold',
            'product_sale_elements.preorder_limit',
            'product_sale_elements.quantity',
        ], $converted);

        foreach ($converted as $qualified) {
            [$table, $column] = explode('.', $qualified);
            $type = $this->connection()
                ->query("SELECT `DATA_TYPE` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '".$table."' AND `COLUMN_NAME` = '".$column."'")
                ->fetchColumn();

            self::assertSame('double', $type, $qualified.' is not stored in double precision.');
        }
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
        $column = null;

        foreach (explode(";\n", $script) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (!str_contains($sql, 'quantity_to_double')) {
                continue;
            }

            if (1 === preg_match("/`TABLE_NAME` = '(\\w+)' AND `COLUMN_NAME` = '(\\w+)'/", $sql, $match)) {
                [, $table, $column] = $match;
            }

            // The other columns of a cart line converted by the script (`reserved_quantity`) are not the probe's.
            if ('cart_item' === $table && 'quantity' === $column) {
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
