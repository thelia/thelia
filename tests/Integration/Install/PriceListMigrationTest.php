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
use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ConfigTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The statements of the pending update script that give a shop price lists, catalog restrictions
 * and the price display mode.
 *
 * Each case takes the database back to the shape of a shop without them, runs the statements read
 * from the shipped script, and compares what they built with the fresh install they were taken
 * from. A schema change commits on its own, so no transaction wraps the case; whatever happens,
 * the statements are run again afterwards to put the fresh install shape back.
 */
final class PriceListMigrationTest extends IntegrationTestCase
{
    /** The pending script, the newest one, which UpdateScriptTest judges too: it moves on at each release. */
    private const PENDING_SCRIPT = '3.4.0.sql';

    private const BLOCK_TITLES = [
        '-- Price lists, catalog restrictions and price display mode',
        '-- Price display mode and pricing resource',
    ];

    /** Created by the script, children first. */
    private const NEW_TABLES = [
        'price_list_item',
        'price_list_assignment',
        'price_list_i18n',
        'catalog_restriction',
        'price_list',
    ];

    /** Changed by the script, whose whole shape is compared. */
    private const ALTERED_TABLES = ['product', 'product_version', 'cart_item'];

    /** The tables that gain `price_display_mode`. */
    private const PRICE_DISPLAY_TABLES = ['product', 'product_version'];

    protected bool $useTransaction = false;

    /** @var array<string, array<string, mixed>> */
    private array $freshInstallShape = [];

    /** What the fresh install seeds, read before the database is taken back to a shop without price lists. */
    private string $freshInstallSetting = '';

    private int $freshInstallResources = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freshInstallShape = $this->shapeOfTheTables();
        $this->freshInstallSetting = implode(',', $this->column("SELECT `value` FROM `config` WHERE `name` = 'price_display_mode'"));
        $this->freshInstallResources = (int) $this->column("SELECT COUNT(*) FROM `resource` r JOIN `resource_i18n` i ON i.`id` = r.`id` WHERE r.`code` = 'admin.pricing'")[0];

        $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->takeTheDatabaseBackToAShopWithoutPriceLists();
    }

    protected function tearDown(): void
    {
        $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->takeTheDatabaseBackToAShopWithoutPriceLists();
        $this->runTheStatements();
        $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 1');

        ConfigQuery::resetCache();
        ConfigTableMap::clearInstancePool();

        parent::tearDown();
    }

    public function testTheStatementsBuildWhatTheFreshInstallHolds(): void
    {
        $this->runTheStatements();

        self::assertSame($this->freshInstallShape, $this->shapeOfTheTables());
        self::assertSame(['5'], $this->column($this->countOfTablesNamed(self::NEW_TABLES)));
    }

    /**
     * The activity probe asks, on every front request, whether any product states a mode of its
     * own: an index on the column answers that without reading the table.
     */
    public function testTheProductHoldsAPlainIndexOnItsPriceDisplayMode(): void
    {
        $expected = [['idx_product_price_display_mode', '1', 'price_display_mode']];
        $read = fn (): array => array_map(
            static fn (array $row): array => array_values(array_map(strval(...), $row)),
            $this->connection()->query("SELECT `INDEX_NAME`, `NON_UNIQUE`, GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX`) FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product' AND `INDEX_NAME` = 'idx_product_price_display_mode' GROUP BY `INDEX_NAME`, `NON_UNIQUE`")->fetchAll(\PDO::FETCH_NUM),
        );

        $freshIndexes = array_values(array_filter(
            $this->freshInstallShape['product']['indexes'],
            static fn (array $index): bool => 'idx_product_price_display_mode' === $index['INDEX_NAME'],
        ));
        self::assertSame([['idx_product_price_display_mode', '1', 'price_display_mode']], array_map(static fn (array $index): array => [$index['INDEX_NAME'], (string) $index['NON_UNIQUE'], $index['COLUMNS']], $freshIndexes), 'the fresh install');
        self::assertSame([], $read(), 'the shop taken back to before the update holds none');

        $this->runTheStatements();
        self::assertSame($expected, $read(), 'the update of a shop that had none');

        $this->runTheStatements();
        self::assertSame($expected, $read(), 'the update replayed');
    }

    /**
     * The activity probe asks, on every front request, whether a cart line still carries the trace
     * of a price list: an index on the column answers that without reading the table.
     */
    public function testTheCartLineHoldsAPlainIndexOnTheTraceOfItsPriceList(): void
    {
        $expected = [['idx_cart_item_price_list_id', '1', 'price_list_id']];
        $read = fn (): array => array_map(
            static fn (array $row): array => array_values(array_map(strval(...), $row)),
            $this->connection()->query("SELECT `INDEX_NAME`, `NON_UNIQUE`, GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX`) FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_item' AND `INDEX_NAME` = 'idx_cart_item_price_list_id' GROUP BY `INDEX_NAME`, `NON_UNIQUE`")->fetchAll(\PDO::FETCH_NUM),
        );

        $freshIndexes = array_values(array_filter(
            $this->freshInstallShape['cart_item']['indexes'],
            static fn (array $index): bool => 'idx_cart_item_price_list_id' === $index['INDEX_NAME'],
        ));
        self::assertSame($expected, array_map(static fn (array $index): array => [$index['INDEX_NAME'], (string) $index['NON_UNIQUE'], $index['COLUMNS']], $freshIndexes), 'the fresh install');
        self::assertSame([], $read(), 'the shop taken back to before the update holds none');

        $this->runTheStatements();
        self::assertSame($expected, $read(), 'the update of a shop that had none');

        $this->runTheStatements();
        self::assertSame($expected, $read(), 'the update replayed');

        // No foreign key: the trace must outlive the list it names.
        self::assertSame([], array_filter(
            $this->freshInstallShape['cart_item']['foreignKeys'],
            static fn (array $foreignKey): bool => str_contains((string) $foreignKey['CONSTRAINT_NAME'], 'price_list'),
        ));
    }

    public function testTheStatementsCanBeReplayedWithoutChangingAnything(): void
    {
        $this->runTheStatements();
        $this->runTheStatements();

        self::assertSame($this->freshInstallShape, $this->shapeOfTheTables());
        self::assertSame(['1'], $this->column("SELECT COUNT(*) FROM `config` WHERE `name` = 'price_display_mode'"));
        self::assertSame(['1'], $this->column("SELECT COUNT(*) FROM `resource` WHERE `code` = 'admin.pricing'"));
        self::assertSame(
            ['8'],
            $this->column("SELECT COUNT(*) FROM `resource_i18n` i JOIN `resource` r ON r.`id` = i.`id` WHERE r.`code` = 'admin.pricing'"),
        );
    }

    public function testTheSettingIsSeededAtVisibleAndLabelledSoNoShopChangesBehaviourByBeingUpdated(): void
    {
        $this->runTheStatements();
        ConfigQuery::resetCache();

        self::assertSame('visible', ConfigQuery::read('price_display_mode'));
        self::assertSame(PriceDisplayMode::Visible, PriceDisplayMode::fromShopConfiguration());

        $titles = $this->column("SELECT i.`locale` FROM `config_i18n` i JOIN `config` c ON c.`id` = i.`id` WHERE c.`name` = 'price_display_mode' AND i.`title` <> '' ORDER BY i.`locale`");
        self::assertContains('en_US', $titles);
        self::assertContains('fr_FR', $titles);
    }

    public function testTheUpdateKeepsTheValueAMerchantAlreadySet(): void
    {
        $this->runTheStatements();
        $this->connection()->exec("UPDATE `config` SET `value` = 'logged_in' WHERE `name` = 'price_display_mode'");

        $this->runTheStatements();
        ConfigQuery::resetCache();

        self::assertSame(PriceDisplayMode::LoggedIn, PriceDisplayMode::fromShopConfiguration());
    }

    public function testAnUnknownValueFallsBackOnVisible(): void
    {
        $this->runTheStatements();
        $this->connection()->exec("UPDATE `config` SET `value` = 'nonsense' WHERE `name` = 'price_display_mode'");
        ConfigQuery::resetCache();

        self::assertSame(PriceDisplayMode::Visible, PriceDisplayMode::fromShopConfiguration());
    }

    public function testAFreshInstallSeedsTheSettingAtVisibleAndTheResourceInEveryLanguage(): void
    {
        self::assertSame('visible', $this->freshInstallSetting);
        self::assertSame(8, $this->freshInstallResources);
    }

    private function takeTheDatabaseBackToAShopWithoutPriceLists(): void
    {
        $this->connection()->exec("DELETE FROM `config_i18n` WHERE `id` IN (SELECT `id` FROM `config` WHERE `name` = 'price_display_mode')");
        $this->connection()->exec("DELETE FROM `config` WHERE `name` = 'price_display_mode'");
        $this->connection()->exec("DELETE FROM `resource_i18n` WHERE `id` IN (SELECT `id` FROM `resource` WHERE `code` = 'admin.pricing')");
        $this->connection()->exec("DELETE FROM `resource` WHERE `code` = 'admin.pricing'");

        foreach (self::PRICE_DISPLAY_TABLES as $table) {
            if ($this->exists('COLUMNS', $table, 'COLUMN_NAME', 'price_display_mode')) {
                $this->connection()->exec(\sprintf('ALTER TABLE `%s` DROP COLUMN `price_display_mode`', $table));
            }
        }

        // The index of the column goes with it.
        if ($this->exists('COLUMNS', 'cart_item', 'COLUMN_NAME', 'price_list_id')) {
            $this->connection()->exec('ALTER TABLE `cart_item` DROP COLUMN `price_list_id`');
        }

        foreach (self::NEW_TABLES as $table) {
            $this->connection()->exec(\sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }

        ConfigQuery::resetCache();
        ConfigTableMap::clearInstancePool();
    }

    private function runTheStatements(): void
    {
        foreach ($this->statements() as $statement) {
            $this->connection()->exec($statement);
        }
    }

    /**
     * The statements of the script an update runs that concern price lists: the two sections
     * named by BLOCK_TITLES, and the statements that add `price_display_mode` to the product
     * tables, which sit after the bundle columns the AFTER clause names.
     *
     * @return list<string>
     */
    private function statements(): array
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.self::PENDING_SCRIPT);

        $statements = [];

        foreach (self::BLOCK_TITLES as $title) {
            $start = strpos($script, $title);
            self::assertIsInt($start, \sprintf('The pending update script no longer holds the section "%s".', $title));

            // A section runs up to the header of the next one, or to the end of the script.
            $headerEnd = strpos($script, "\n-- ----", $start);
            self::assertIsInt($headerEnd);
            $end = strpos($script, "\n-- ----", $headerEnd + 1);
            $end = false !== $end ? $end : strpos($script, 'SET FOREIGN_KEY_CHECKS = 1', $headerEnd);
            self::assertIsInt($end);

            array_push($statements, ...$this->split(substr($script, $headerEnd, $end - $headerEnd)));
        }

        $all = $this->split($script);
        $found = [];

        foreach ($all as $index => $statement) {
            foreach (['price_display_mode', 'price_list_id'] as $column) {
                if (str_starts_with($statement, 'SET @statement') && str_contains($statement, '`'.$column.'`') && str_contains($statement, 'ALTER TABLE')) {
                    // SET @add_column (or @add_index), SET @statement, PREPARE, EXECUTE, DEALLOCATE.
                    array_push($statements, ...\array_slice($all, $index - 1, 5));
                    $kind = str_contains($statement, 'ADD INDEX') ? 'index' : 'column';
                    $found[$column.' '.$kind] = ($found[$column.' '.$kind] ?? 0) + 1;
                }
            }
        }

        self::assertSame(\count(self::PRICE_DISPLAY_TABLES), $found['price_display_mode column'] ?? 0, 'The script must add price_display_mode to each product table.');
        self::assertSame(1, $found['price_display_mode index'] ?? 0, 'The script must index price_display_mode on the product table, once.');
        self::assertSame(1, $found['price_list_id column'] ?? 0, 'The script must add price_list_id to the cart line table, once.');
        self::assertSame(1, $found['price_list_id index'] ?? 0, 'The script must index price_list_id on the cart line table, once.');

        return $statements;
    }

    /**
     * @return list<string>
     */
    private function split(string $script): array
    {
        $statements = [];

        foreach (explode(";\n", $script) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if ('' !== $sql) {
                $statements[] = $sql;
            }
        }

        return $statements;
    }

    /**
     * Columns in their order, indexes and foreign keys of every table the script builds or changes.
     *
     * @return array<string, array<string, mixed>>
     */
    private function shapeOfTheTables(): array
    {
        $shape = [];

        foreach ([...self::ALTERED_TABLES, ...self::NEW_TABLES] as $table) {
            $columns = $this->connection()->prepare('SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `IS_NULLABLE`, `COLUMN_DEFAULT`, `COLUMN_COMMENT` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table ORDER BY `ORDINAL_POSITION`');
            $columns->execute(['table' => $table]);

            $indexes = $this->connection()->prepare('SELECT `INDEX_NAME`, `NON_UNIQUE`, GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX`) AS `COLUMNS` FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table GROUP BY `INDEX_NAME`, `NON_UNIQUE` ORDER BY `INDEX_NAME`');
            $indexes->execute(['table' => $table]);

            $foreignKeys = $this->connection()->prepare('SELECT `CONSTRAINT_NAME`, `REFERENCED_TABLE_NAME`, `DELETE_RULE`, `UPDATE_RULE` FROM `information_schema`.`REFERENTIAL_CONSTRAINTS` WHERE `CONSTRAINT_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table ORDER BY `CONSTRAINT_NAME`');
            $foreignKeys->execute(['table' => $table]);

            $shape[$table] = [
                // MySQL 8 drops the display width of an integer type that MariaDB still shows.
                'columns' => array_map(
                    static fn (array $column): array => [
                        ...$column,
                        'COLUMN_TYPE' => preg_replace('/^((?:tiny|small|medium|big)?int)\(\d+\)/', '$1', (string) $column['COLUMN_TYPE']),
                    ],
                    $columns->fetchAll(\PDO::FETCH_ASSOC),
                ),
                'indexes' => $indexes->fetchAll(\PDO::FETCH_ASSOC),
                // InnoDB treats NO ACTION and RESTRICT alike, and an ALTER ... ADD CONSTRAINT reports
                // the first where the CREATE TABLE of the fresh install reports the second.
                'foreignKeys' => array_map(
                    static fn (array $foreignKey): array => str_replace('NO ACTION', 'RESTRICT', $foreignKey),
                    $foreignKeys->fetchAll(\PDO::FETCH_ASSOC),
                ),
            ];
        }

        return $shape;
    }

    /**
     * @param list<string> $tables
     */
    private function countOfTablesNamed(array $tables): string
    {
        return \sprintf(
            "SELECT COUNT(*) FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` IN ('%s')",
            implode("','", $tables),
        );
    }

    private function exists(string $informationSchemaTable, string $table, string $nameColumn, string $name): bool
    {
        $statement = $this->connection()->prepare(\sprintf(
            'SELECT COUNT(*) FROM `information_schema`.`%s` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table AND `%s` = :name',
            $informationSchemaTable,
            $nameColumn,
        ));
        $statement->execute(['table' => $table, 'name' => $name]);

        return 0 < (int) $statement->fetchColumn();
    }

    /**
     * @return list<string>
     */
    private function column(string $sql): array
    {
        return array_map(strval(...), $this->connection()->query($sql)->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getWriteConnection(ConfigTableMap::DATABASE_NAME);
    }
}
