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
use Thelia\Model\Map\SubscriptionTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The subscriptions block of the pending update script, played on a shop that has none:
 * it builds the tables and the column the fresh install has, with the same keys, seeds the
 * retry settings with their titles, and plays again without changing anything.
 *
 * It runs DDL, which commits: no test transaction, and the fresh install shape is put back
 * by the block itself whatever the case did.
 */
final class SubscriptionSchemaMigrationTest extends IntegrationTestCase
{
    private const string PENDING_SCRIPT = '3.4.0.sql';

    private const string BLOCK_TITLE = '-- Subscriptions with recurring billing';

    private const array NEW_TABLES = ['subscription_plan', 'subscription_plan_i18n', 'subscription_plan_price', 'subscription', 'subscription_order', 'subscription_history'];

    protected bool $useTransaction = false;

    /** @var array<string, array<string, mixed>> */
    private array $freshInstallShape = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->freshInstallShape = $this->shapeOfTheTables();

        $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->takeTheDatabaseBackToAShopWithoutSubscriptions();
    }

    protected function tearDown(): void
    {
        $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->takeTheDatabaseBackToAShopWithoutSubscriptions();
        $this->runTheBlock();
        $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 1');

        SubscriptionTableMap::clearInstancePool();

        parent::tearDown();
    }

    public function testTheBlockBuildsWhatTheFreshInstallHolds(): void
    {
        $this->runTheBlock();

        self::assertSame($this->freshInstallShape, $this->shapeOfTheTables());
    }

    /**
     * The key that keeps a second order from being placed for the same installment.
     */
    public function testAnInstallmentIsRecordedOncePerSubscription(): void
    {
        $this->runTheBlock();

        self::assertSame(
            ['subscription_order_installment_unique' => '0:subscription_id,installment_number'],
            array_column(
                array_map(
                    static fn (array $index): array => ['name' => $index['INDEX_NAME'], 'value' => $index['NON_UNIQUE'].':'.$index['COLUMNS']],
                    array_filter($this->shapeOfTheTables()['subscription_order']['indexes'], static fn (array $index): bool => 'subscription_order_installment_unique' === $index['INDEX_NAME']),
                ),
                'value',
                'name',
            ),
        );
    }

    public function testTheBlockPlaysAgainWithoutChangingAnythingAndKeepsASettingTheMerchantChose(): void
    {
        $this->runTheBlock();
        $this->connection()->exec("UPDATE `config` SET `value` = '5' WHERE `name` = 'subscription_retry_attempts'");
        $shape = $this->shapeOfTheTables();

        $this->runTheBlock();

        self::assertSame($shape, $this->shapeOfTheTables());
        self::assertSame(['5'], $this->column("SELECT `value` FROM `config` WHERE `name` = 'subscription_retry_attempts'"));
        self::assertSame(['2'], $this->column("SELECT `value` FROM `config` WHERE `name` = 'subscription_retry_interval_days'"));
        self::assertSame(['1'], $this->column("SELECT COUNT(*) FROM `config` WHERE `name` = 'subscription_retry_attempts'"));
        self::assertSame(
            ['de_DE', 'en_US', 'es_ES', 'fr_FR', 'nl_NL', 'ru_RU'],
            $this->column("SELECT `config_i18n`.`locale` FROM `config_i18n` INNER JOIN `config` ON `config`.`id` = `config_i18n`.`id` WHERE `config`.`name` = 'subscription_retry_interval_days' AND `config_i18n`.`title` <> '' ORDER BY `config_i18n`.`locale`"),
        );
    }

    private function takeTheDatabaseBackToAShopWithoutSubscriptions(): void
    {
        if ($this->exists('TABLE_CONSTRAINTS', 'cart_item', 'CONSTRAINT_NAME', 'fk_cart_item_subscription_plan_id')) {
            $this->connection()->exec('ALTER TABLE `cart_item` DROP FOREIGN KEY `fk_cart_item_subscription_plan_id`');
        }

        if ($this->exists('STATISTICS', 'cart_item', 'INDEX_NAME', 'fk_cart_item_subscription_plan_id_idx')) {
            $this->connection()->exec('ALTER TABLE `cart_item` DROP INDEX `fk_cart_item_subscription_plan_id_idx`');
        }

        if ($this->exists('COLUMNS', 'cart_item', 'COLUMN_NAME', 'subscription_plan_id')) {
            $this->connection()->exec('ALTER TABLE `cart_item` DROP COLUMN `subscription_plan_id`');
        }

        foreach (array_reverse(self::NEW_TABLES) as $table) {
            $this->connection()->exec(\sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }

        $this->connection()->exec("DELETE `config_i18n` FROM `config_i18n` INNER JOIN `config` ON `config`.`id` = `config_i18n`.`id` WHERE `config`.`name` IN ('subscription_retry_attempts', 'subscription_retry_interval_days')");
        $this->connection()->exec("DELETE FROM `config` WHERE `name` IN ('subscription_retry_attempts', 'subscription_retry_interval_days')");
    }

    private function runTheBlock(): void
    {
        foreach ($this->blockStatements() as $statement) {
            $this->connection()->exec($statement);
        }
    }

    /**
     * @return list<string>
     */
    private function blockStatements(): array
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.self::PENDING_SCRIPT);
        $start = strpos($script, self::BLOCK_TITLE);
        self::assertIsInt($start, 'The pending update script gives no subscriptions.');

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
     * @return array<string, array<string, mixed>>
     */
    private function shapeOfTheTables(): array
    {
        $shape = [];

        foreach ([...self::NEW_TABLES, 'cart_item'] as $table) {
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

    private function exists(string $informationSchemaTable, string $table, string $nameColumn, string $name): bool
    {
        $statement = $this->connection()->prepare(\sprintf(
            'SELECT COUNT(*) FROM `information_schema`.`%s` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table AND `%s` = :name',
            $informationSchemaTable,
            $nameColumn,
        ));
        $statement->execute(['table' => $table, 'name' => $name]);

        return (int) $statement->fetchColumn() > 0;
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
        return Propel::getWriteConnection(SubscriptionTableMap::DATABASE_NAME);
    }
}
