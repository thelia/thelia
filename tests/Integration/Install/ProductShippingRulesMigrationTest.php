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
use Thelia\Model\Map\ProductTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The block of the pending update script that gives a 3.2 shop the shipping rules of a
 * product: three columns on `product` and on `product_version`, and the table of the
 * excluded carriers.
 *
 * Each case takes the database back to its 3.2 shape, runs the block read from the shipped
 * script, and compares what it built with the fresh install. A schema change commits on its
 * own, so no transaction wraps the case; whatever happens, the fresh install definition is
 * put back afterwards.
 */
final class ProductShippingRulesMigrationTest extends IntegrationTestCase
{
    /** The pending script, the one UpdateScriptTest names: it moves on at each release. */
    private const PENDING_SCRIPT = '3.3.0.sql';

    private const BLOCK_TITLE = '-- Shipping rules of a product';

    /** Column => definition of the fresh install, in setup/thelia.sql, and the column it follows. */
    private const COLUMNS = [
        'shipping_surcharge' => ["DECIMAL(16,6) DEFAULT 0 NOT NULL COMMENT 'fixed amount added to the carrier postage, untaxed, in the default currency'", 'guest_checkout_forbidden'],
        'shipping_surcharge_mode' => ["TINYINT DEFAULT 0 NOT NULL COMMENT '0: the surcharge is charged once per cart line, 1: once per unit'", 'shipping_surcharge'],
        'free_shipping' => ["TINYINT DEFAULT 0 NOT NULL COMMENT 'the product is left out of the postage the carrier quotes'", 'shipping_surcharge_mode'],
    ];

    private const EXCLUSION_TABLE = <<<'SQL'
        CREATE TABLE IF NOT EXISTS `product_excluded_delivery_module`
        (
            `id` INTEGER NOT NULL AUTO_INCREMENT,
            `product_id` INTEGER NOT NULL,
            `module_id` INTEGER NOT NULL,
            `created_at` DATETIME,
            `updated_at` DATETIME,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `product_excluded_delivery_module_UNIQUE` (`product_id`, `module_id`),
            INDEX `idx_product_excluded_delivery_module_module_id` (`module_id`),
            CONSTRAINT `fk_product_excluded_delivery_module_product_id`
                FOREIGN KEY (`product_id`)
                REFERENCES `product` (`id`)
                ON UPDATE RESTRICT
                ON DELETE CASCADE,
            CONSTRAINT `fk_product_excluded_delivery_module_module_id`
                FOREIGN KEY (`module_id`)
                REFERENCES `module` (`id`)
                ON UPDATE RESTRICT
                ON DELETE CASCADE
        ) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC
        SQL;

    protected bool $useTransaction = false;

    protected function setUp(): void
    {
        parent::setUp();

        // The shape of a 3.2 shop: none of it exists yet.
        $this->connection()->exec('DROP TABLE IF EXISTS `product_excluded_delivery_module`');

        foreach (['product', 'product_version'] as $table) {
            foreach (array_reverse(array_keys(self::COLUMNS)) as $column) {
                if ($this->hasColumn($table, $column)) {
                    $this->connection()->exec(\sprintf('ALTER TABLE `%s` DROP COLUMN `%s`', $table, $column));
                }
            }
        }
    }

    protected function tearDown(): void
    {
        // Put back what the fresh install has, whatever the block did or failed to do.
        foreach (['product', 'product_version'] as $table) {
            foreach (self::COLUMNS as $column => [$definition, $after]) {
                if (!$this->hasColumn($table, $column)) {
                    $this->connection()->exec(\sprintf('ALTER TABLE `%s` ADD `%s` %s AFTER `%s`', $table, $column, $definition, $after));
                }
            }
        }

        $this->connection()->exec(self::EXCLUSION_TABLE);

        ProductTableMap::clearInstancePool();

        parent::tearDown();
    }

    public function testTheBlockGivesA32ShopTheColumnsAndTheTableOfTheFreshInstall(): void
    {
        $this->runTheBlock();

        foreach (['product', 'product_version'] as $table) {
            $columns = $this->columnsOf($table);

            self::assertSame(['decimal(16,6)', 'NO', '0.000000'], $this->shapeOf($columns, 'shipping_surcharge'), $table);
            self::assertSame(['tinyint', 'NO', '0'], $this->shapeOf($columns, 'shipping_surcharge_mode'), $table);
            self::assertSame(['tinyint', 'NO', '0'], $this->shapeOf($columns, 'free_shipping'), $table);

            // In the order of the fresh install, after the guest checkout flag.
            $names = array_keys($columns);
            $after = array_search('guest_checkout_forbidden', $names, true);
            self::assertIsInt($after, $table);
            self::assertSame(['shipping_surcharge', 'shipping_surcharge_mode', 'free_shipping'], \array_slice($names, $after + 1, 3), $table);
        }

        self::assertSame(['product_id', 'module_id'], $this->uniqueIndexColumns('product_excluded_delivery_module_UNIQUE'));
        self::assertSame([
            'fk_product_excluded_delivery_module_module_id' => ['module', 'CASCADE', 'RESTRICT'],
            'fk_product_excluded_delivery_module_product_id' => ['product', 'CASCADE', 'RESTRICT'],
        ], $this->foreignKeys());
    }

    public function testTheBlockCanBeReplayedAndLeavesTheProductsWithoutRule(): void
    {
        $this->runTheBlock();
        $this->runTheBlock();

        $productsWithARule = $this->connection()
            ->query('SELECT COUNT(*) FROM `product` WHERE `shipping_surcharge` <> 0 OR `shipping_surcharge_mode` <> 0 OR `free_shipping` <> 0')
            ->fetchColumn();
        $exclusions = $this->connection()->query('SELECT COUNT(*) FROM `product_excluded_delivery_module`')->fetchColumn();

        self::assertSame(0, (int) $productsWithARule, 'An updated shop charges what it charged before.');
        self::assertSame(0, (int) $exclusions);
        self::assertCount(3, array_intersect(array_keys($this->columnsOf('product')), array_keys(self::COLUMNS)));
    }

    private function runTheBlock(): void
    {
        foreach ($this->blockStatements() as $statement) {
            $this->connection()->exec($statement);
        }
    }

    /**
     * The statements of the block, read from the script an update runs.
     *
     * @return list<string>
     */
    private function blockStatements(): array
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.self::PENDING_SCRIPT);
        $start = strpos($script, self::BLOCK_TITLE);
        self::assertIsInt($start, 'The pending update script gives no shipping rules to a product.');

        // The block runs up to the header of the next block, or to the end of the script.
        $headerEnd = strpos($script, "\n-- ----", $start);
        self::assertIsInt($headerEnd);
        $end = strpos($script, "\n-- ----", $headerEnd + 1);
        $end = false !== $end ? $end : strpos($script, 'SET FOREIGN_KEY_CHECKS = 1', $headerEnd);
        self::assertIsInt($end);

        $statements = [];

        foreach (explode(";\n", substr($script, $headerEnd, $end - $headerEnd)) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if ('' !== $sql) {
                $statements[] = $sql;
            }
        }

        self::assertNotEmpty($statements);

        return $statements;
    }

    /**
     * @return array<string, array{COLUMN_TYPE: string, IS_NULLABLE: string, COLUMN_DEFAULT: ?string}> in their order
     */
    private function columnsOf(string $table): array
    {
        $statement = $this->connection()->prepare('SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `IS_NULLABLE`, `COLUMN_DEFAULT` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table ORDER BY `ORDINAL_POSITION`');
        $statement->execute(['table' => $table]);

        $columns = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $columns[(string) $row['COLUMN_NAME']] = $row;
        }

        return $columns;
    }

    /**
     * @param array<string, array{COLUMN_TYPE: string, IS_NULLABLE: string, COLUMN_DEFAULT: ?string}> $columns
     *
     * @return array{0: string, 1: string, 2: ?string}|null
     */
    private function shapeOf(array $columns, string $column): ?array
    {
        if (!isset($columns[$column])) {
            return null;
        }

        // MySQL 8 drops the display width of an integer type that MariaDB still shows, and
        // MariaDB quotes nothing for a numeric default while MySQL may: compare the shape only.
        $type = preg_replace('/^((?:tiny|small|medium|big)?int)\(\d+\)/', '$1', (string) $columns[$column]['COLUMN_TYPE']) ?? '';

        return [$type, $columns[$column]['IS_NULLABLE'], trim((string) $columns[$column]['COLUMN_DEFAULT'], "'")];
    }

    /**
     * @return list<string>
     */
    private function uniqueIndexColumns(string $index): array
    {
        $statement = $this->connection()->prepare("SELECT `COLUMN_NAME` FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_excluded_delivery_module' AND `INDEX_NAME` = :index AND `NON_UNIQUE` = 0 ORDER BY `SEQ_IN_INDEX`");
        $statement->execute(['index' => $index]);

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}> constraint => referenced table, on delete, on update
     */
    private function foreignKeys(): array
    {
        $rows = $this->connection()
            ->query("SELECT `CONSTRAINT_NAME`, `REFERENCED_TABLE_NAME`, `DELETE_RULE`, `UPDATE_RULE` FROM `information_schema`.`REFERENTIAL_CONSTRAINTS` WHERE `CONSTRAINT_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_excluded_delivery_module' ORDER BY `CONSTRAINT_NAME`")
            ->fetchAll(\PDO::FETCH_ASSOC);

        $foreignKeys = [];

        foreach ($rows as $row) {
            $foreignKeys[(string) $row['CONSTRAINT_NAME']] = [(string) $row['REFERENCED_TABLE_NAME'], (string) $row['DELETE_RULE'], (string) $row['UPDATE_RULE']];
        }

        return $foreignKeys;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return \array_key_exists($column, $this->columnsOf($table));
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getWriteConnection(ProductTableMap::DATABASE_NAME);
    }
}
