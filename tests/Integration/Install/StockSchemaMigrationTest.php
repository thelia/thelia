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
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The stock blocks of the pending update script.
 *
 * Each case takes the database back to the shape of a shop without the stock
 * columns and seeds, runs the blocks read from the shipped script (twice for the
 * replay), and compares the columns it built with the fresh install they were
 * taken from. A schema change commits on its own, so no transaction wraps the
 * cases; whatever happens, the blocks are run again afterwards to put the fresh
 * install shape back.
 */
final class StockSchemaMigrationTest extends IntegrationTestCase
{
    private const PENDING_SCRIPT = '3.4.0.sql';

    private const SCHEMA_BLOCK_TITLE = '-- Stock: sale at zero stock, reservation, low stock threshold';
    private const SEED_BLOCK_TITLE = '-- Stock settings and the low stock alert message';
    private const BUNDLE_BLOCK_TITLE = '-- Product bundles';

    private const NEW_COLUMNS = [
        'product_sale_elements' => ['out_of_stock_policy', 'preorder_limit', 'restock_date', 'low_stock_threshold'],
        'cart_item' => ['reserved_until', 'reserved_quantity', 'reserved_since'],
        'order_product' => ['is_preorder', 'restock_date'],
    ];

    private const CONFIG_NAMES = ['stock_reservation_enabled', 'stock_reservation_scope', 'stock_reservation_duration', 'low_stock_threshold'];

    protected bool $useTransaction = false;

    /** @var array<string, list<array<string, mixed>>> */
    private array $freshInstallShape = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->freshInstallShape = $this->shape();
        $this->takeTheDatabaseBackToAShopWithoutStockSettings();
    }

    protected function tearDown(): void
    {
        $this->takeTheDatabaseBackToAShopWithoutStockSettings();
        $this->runBlock(self::SCHEMA_BLOCK_TITLE);
        $this->runBlock(self::SEED_BLOCK_TITLE);

        parent::tearDown();
    }

    public function testTheSchemaBlockBuildsWhatTheFreshInstallHolds(): void
    {
        $this->runBlock(self::SCHEMA_BLOCK_TITLE);

        self::assertSame($this->freshInstallShape, $this->shape());
    }

    public function testTheSchemaBlockCanBeReplayed(): void
    {
        $this->runBlock(self::SCHEMA_BLOCK_TITLE);
        $this->runBlock(self::SCHEMA_BLOCK_TITLE);

        self::assertSame($this->freshInstallShape, $this->shape());
    }

    /**
     * A shop of 3.3 has neither the composition of a bundle line nor the reservation on its cart
     * lines: the stock block runs before the bundle block, which adds the composition after it.
     * The columns still come in the order of a fresh install, and a replay changes nothing.
     */
    public function testOnTheCartLinesOfA33ShopTheScriptBuildsTheColumnsOfAFreshInstall(): void
    {
        if ([] !== $this->column("SHOW COLUMNS FROM `cart_item` LIKE 'bundle_composition'")) {
            $this->connection()->exec('ALTER TABLE `cart_item` DROP COLUMN `bundle_composition`');
        }

        try {
            $this->runBlock(self::SCHEMA_BLOCK_TITLE);
            $this->runBlock(self::BUNDLE_BLOCK_TITLE);

            self::assertSame($this->freshInstallShape, $this->shape());

            $this->runBlock(self::SCHEMA_BLOCK_TITLE);
            $this->runBlock(self::BUNDLE_BLOCK_TITLE);

            self::assertSame($this->freshInstallShape, $this->shape());
        } finally {
            // Whatever happened, the composition of the bundle lines is back.
            $this->runBlock(self::BUNDLE_BLOCK_TITLE);
        }
    }

    /**
     * A development database that already has the date of the reservation but not the quantity the
     * listeners were told: the block adds the quantity right after the date, as a fresh install has it.
     */
    public function testAShopThatHasTheReservationDateButNotTheQuantityGetsItRightAfterTheDate(): void
    {
        $this->runBlock(self::SCHEMA_BLOCK_TITLE);
        $this->connection()->exec('ALTER TABLE `cart_item` DROP COLUMN `reserved_quantity`');
        self::assertSame([], $this->column("SHOW COLUMNS FROM `cart_item` LIKE 'reserved_quantity'"));

        $this->runBlock(self::SCHEMA_BLOCK_TITLE);

        self::assertSame($this->freshInstallShape, $this->shape());
        $columns = $this->column("SELECT `COLUMN_NAME` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_item' ORDER BY `ORDINAL_POSITION`");
        self::assertSame(
            ['reserved_until', 'reserved_quantity', 'reserved_since'],
            \array_slice($columns, (int) array_search('reserved_until', $columns, true), 3),
        );
    }

    public function testTheSeedBlockSeedsEachSettingAndMessageOnceWhateverTheReplays(): void
    {
        $this->runBlock(self::SEED_BLOCK_TITLE);
        $this->runBlock(self::SEED_BLOCK_TITLE);

        $names = "'".implode("','", self::CONFIG_NAMES)."'";

        self::assertSame(
            ['low_stock_threshold=5', 'stock_reservation_duration=15', 'stock_reservation_enabled=0', 'stock_reservation_scope=checkout'],
            $this->column("SELECT CONCAT(`name`, '=', `value`) FROM `config` WHERE `name` IN ($names) ORDER BY `name`"),
        );
        self::assertSame(
            ['4'],
            $this->column("SELECT COUNT(DISTINCT c.`id`) FROM `config_i18n` i JOIN `config` c ON c.`id` = i.`id` WHERE c.`name` IN ($names) AND i.`locale` = 'en_US' AND i.`title` <> ''"),
            'Each setting has an English title.',
        );
        self::assertSame(
            ['1'],
            $this->column("SELECT COUNT(*) FROM `message` WHERE `name` = 'low_stock_alert'"),
        );
        self::assertSame(
            ['8'],
            $this->column("SELECT COUNT(*) FROM `message_i18n` i JOIN `message` m ON m.`id` = i.`id` WHERE m.`name` = 'low_stock_alert'"),
        );
    }

    public function testAShopThatChoseAValueKeepsIt(): void
    {
        $this->runBlock(self::SEED_BLOCK_TITLE);
        $this->connection()->exec("UPDATE `config` SET `value` = '30' WHERE `name` = 'stock_reservation_duration'");

        $this->runBlock(self::SEED_BLOCK_TITLE);

        self::assertSame(['30'], $this->column("SELECT `value` FROM `config` WHERE `name` = 'stock_reservation_duration'"));
    }

    private function takeTheDatabaseBackToAShopWithoutStockSettings(): void
    {
        $connection = $this->connection();

        foreach (['idx_cart_item_pse_reserved_until', 'idx_cart_item_reserved_until'] as $index) {
            if ([] !== $this->column("SHOW INDEX FROM `cart_item` WHERE `Key_name` = '$index'")) {
                $connection->exec("ALTER TABLE `cart_item` DROP INDEX `$index`");
            }
        }

        foreach (self::NEW_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if ([] !== $this->column("SHOW COLUMNS FROM `$table` LIKE '$column'")) {
                    $connection->exec("ALTER TABLE `$table` DROP COLUMN `$column`");
                }
            }
        }

        $names = "'".implode("','", self::CONFIG_NAMES)."'";
        $connection->exec("DELETE `config_i18n` FROM `config_i18n` JOIN `config` ON `config`.`id` = `config_i18n`.`id` WHERE `config`.`name` IN ($names)");
        $connection->exec("DELETE FROM `config` WHERE `name` IN ($names)");
        $connection->exec("DELETE `message_i18n` FROM `message_i18n` JOIN `message` ON `message`.`id` = `message_i18n`.`id` WHERE `message`.`name` = 'low_stock_alert'");
        $connection->exec("DELETE FROM `message` WHERE `name` = 'low_stock_alert'");
    }

    /**
     * What a fresh install holds for the tables the blocks change.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function shape(): array
    {
        $shape = [];

        foreach (array_keys(self::NEW_COLUMNS) as $table) {
            $shape[$table] = $this->connection()->query(
                "SELECT `COLUMN_NAME`, `ORDINAL_POSITION`, `COLUMN_TYPE`, `IS_NULLABLE`, `COLUMN_DEFAULT`, `COLUMN_COMMENT` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '$table' ORDER BY `ORDINAL_POSITION`"
            )->fetchAll(\PDO::FETCH_ASSOC);
        }

        $shape['index'] = $this->connection()->query(
            "SELECT `INDEX_NAME`, `SEQ_IN_INDEX`, `COLUMN_NAME` FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_item' AND `INDEX_NAME` IN ('idx_cart_item_pse_reserved_until', 'idx_cart_item_reserved_until') ORDER BY `INDEX_NAME`, `SEQ_IN_INDEX`"
        )->fetchAll(\PDO::FETCH_ASSOC);

        return $shape;
    }

    private function runBlock(string $title): void
    {
        foreach ($this->blockStatements($title) as $statement) {
            $this->connection()->exec($statement);
        }
    }

    /**
     * @return list<string>
     */
    private function blockStatements(string $title): array
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.self::PENDING_SCRIPT);
        $start = strpos($script, $title);
        self::assertIsInt($start, 'The pending update script has no such block: '.$title);

        // The block runs from the end of its header to the header of the next block, or to the end of the script.
        $headerEnd = strpos($script, "\n-- ----", $start);
        self::assertIsInt($headerEnd);
        $end = strpos($script, "\n-- ----", $headerEnd + 1);
        $end = false !== $end ? $end : strpos($script, 'SET FOREIGN_KEY_CHECKS = 1', $headerEnd);
        self::assertIsInt($end);

        $statements = [];

        foreach (explode(";\n", substr($script, $headerEnd + 1, $end - $headerEnd - 1)) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if ('' !== $sql) {
                $statements[] = $sql;
            }
        }

        self::assertNotEmpty($statements);

        return $statements;
    }

    /**
     * @return list<string>
     */
    private function column(string $sql): array
    {
        return array_map('strval', $this->connection()->query($sql)->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);
    }
}
