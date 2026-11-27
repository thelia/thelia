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

namespace Thelia\Tests\Integration\Quote;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Test\IntegrationTestCase;

/**
 * The block of the pending update script that gives a shop the negotiated quotes.
 *
 * Each case takes the database back to the shape of a shop with no quote, runs the block read
 * from the shipped script, and compares what it built with the fresh install it was taken from.
 * A schema change commits on its own, so no transaction wraps the case; whatever happens, the
 * block is run again afterwards to put the fresh install shape back.
 */
final class QuoteMigrationTest extends IntegrationTestCase
{
    /** The pending script, the newest one: it moves on at each release. */
    private const PENDING_SCRIPT = '3.5.0.sql';

    private const BLOCK_TITLE = '-- Negotiated quotes';

    private const COMPANY_BLOCK_TITLE = '-- Company accounts';

    /** Created by the block, children first. */
    private const NEW_TABLES = ['quote_revision_address', 'quote_item_request', 'quote_item', 'quote_revision', 'quote'];

    /** Changed by the block, whose whole shape is compared. */
    private const ALTERED_TABLES = ['cart', 'cart_item', 'order', 'order_version'];

    private const SETTINGS = ['quote_mode', 'quote_validity_days', 'quote_ref_format'];

    private const MESSAGES = ['quote_requested', 'quote_sent', 'quote_countered', 'quote_accepted', 'quote_rejected'];

    private const RESOURCE = 'admin.quote';

    protected bool $useTransaction = false;

    /**
     * Read from the database by the first case of the process, before any case has run the block
     * again: a later case would otherwise take the shape its predecessor left as the fresh one.
     *
     * @var array<string, array<string, mixed>>|null
     */
    private static ?array $freshInstallShape = null;

    /** @var array<string, list<array<string, mixed>>>|null */
    private static ?array $freshInstallSeeds = null;

    /** @var array<string, list<array<string, mixed>>>|null */
    private static ?array $freshInstallRoles = null;

    protected function setUp(): void
    {
        parent::setUp();

        self::$freshInstallShape ??= $this->shapeOfTheTables();
        self::$freshInstallSeeds ??= $this->seeds();
        self::$freshInstallRoles ??= $this->sharedRoles();

        $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->takeTheDatabaseBackToAShopWithoutQuotes();
    }

    protected function tearDown(): void
    {
        try {
            $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 0');
            $this->takeTheDatabaseBackToAShopWithoutQuotes();
            $this->runTheBlock(self::BLOCK_TITLE);
            $this->putTheSeedsBackUnderTheirIds();
            $this->putTheSharedRolesBack();
        } finally {
            $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        parent::tearDown();
    }

    public function testTheBlockBuildsWhatTheFreshInstallHolds(): void
    {
        $this->runTheBlock(self::BLOCK_TITLE);

        self::assertSame(self::$freshInstallShape, $this->shapeOfTheTables());
    }

    public function testTheBlockCanBeRunTwiceWithoutErrorAndLeavesTheSameShapeAndTheSameRows(): void
    {
        $this->runTheBlock(self::BLOCK_TITLE);
        $this->runTheBlock(self::BLOCK_TITLE);

        self::assertSame(self::$freshInstallShape, $this->shapeOfTheTables());
        self::assertSame(['3'], $this->column("SELECT COUNT(*) FROM `config` WHERE `name` IN ('quote_mode', 'quote_validity_days', 'quote_ref_format')"));
        self::assertSame(['24'], $this->column("SELECT COUNT(*) FROM `config_i18n` i JOIN `config` c ON c.`id` = i.`id` WHERE c.`name` IN ('quote_mode', 'quote_validity_days', 'quote_ref_format')"));
        self::assertSame(['5'], $this->column("SELECT COUNT(*) FROM `message` WHERE `name` LIKE 'quote\\_%'"));
        self::assertSame(['40'], $this->column("SELECT COUNT(*) FROM `message_i18n` i JOIN `message` m ON m.`id` = i.`id` WHERE m.`name` LIKE 'quote\\_%'"));
        self::assertSame(['1'], $this->column("SELECT COUNT(*) FROM `resource` WHERE `code` = 'admin.quote'"));
        self::assertSame(['8'], $this->column("SELECT COUNT(*) FROM `resource_i18n` i JOIN `resource` r ON r.`id` = i.`id` WHERE r.`code` = 'admin.quote'"));
    }

    public function testTheSettingsMessagesAndResourceAreTheOnesTheFreshInstallSeeds(): void
    {
        $this->runTheBlock(self::BLOCK_TITLE);

        self::assertSame($this->withoutIds(self::$freshInstallSeeds), $this->withoutIds($this->seeds()));
        self::assertSame('disabled', $this->column("SELECT `value` FROM `config` WHERE `name` = 'quote_mode'")[0]);
        self::assertSame('30', $this->column("SELECT `value` FROM `config` WHERE `name` = 'quote_validity_days'")[0]);
        self::assertSame('QUO%08d', $this->column("SELECT `value` FROM `config` WHERE `name` = 'quote_ref_format'")[0]);
    }

    public function testAReplayKeepsTheSettingsAShopChanged(): void
    {
        $this->runTheBlock(self::BLOCK_TITLE);

        $this->connection()->exec("UPDATE `config` SET `value` = 'only' WHERE `name` = 'quote_mode'");
        $this->connection()->exec("UPDATE `config` SET `value` = '45' WHERE `name` = 'quote_validity_days'");
        $this->connection()->exec("UPDATE `message_i18n` i JOIN `message` m ON m.`id` = i.`id` SET i.`subject` = 'Edited' WHERE m.`name` = 'quote_sent' AND i.`locale` = 'en_US'");

        $this->runTheBlock(self::BLOCK_TITLE);

        self::assertSame(['only'], $this->column("SELECT `value` FROM `config` WHERE `name` = 'quote_mode'"));
        self::assertSame(['45'], $this->column("SELECT `value` FROM `config` WHERE `name` = 'quote_validity_days'"));
        self::assertSame(['Edited'], $this->column("SELECT i.`subject` FROM `message_i18n` i JOIN `message` m ON m.`id` = i.`id` WHERE m.`name` = 'quote_sent' AND i.`locale` = 'en_US'"));
        self::assertSame(self::$freshInstallShape, $this->shapeOfTheTables());
    }

    public function testARevisionTableOfAnEarlierFormGainsTheFrozenAmountsTheDraftChoicesAndTheCopyOfTheAddresses(): void
    {
        $this->runTheBlock(self::BLOCK_TITLE);
        $this->putTheRevisionTableBackToItsEarlierForm();
        $this->connection()->exec("INSERT INTO `quote` (`ref`, `customer_id`, `state`, `current_revision`, `currency_id`) VALUES ('MIGRATION-1', 999999, 'quoted', 2, 1)");
        $quoteId = (int) $this->connection()->query("SELECT `id` FROM `quote` WHERE `ref` = 'MIGRATION-1'")->fetchColumn();
        $this->connection()->exec(\sprintf("INSERT INTO `quote_revision` (`quote_id`, `number`, `author`, `state`, `discount`, `postage`, `total_untaxed`, `total_taxed`) VALUES (%d, 2, 'admin', 'sent', 30, 15, 1350, 1620)", $quoteId));
        self::assertNotSame(self::$freshInstallShape, $this->shapeOfTheTables(), 'control: the earlier form is not the fresh one');

        $this->runTheBlock(self::BLOCK_TITLE);

        self::assertSame(self::$freshInstallShape, $this->shapeOfTheTables());
        self::assertSame(['1620.000000'], $this->column(\sprintf('SELECT `total_taxed` FROM `quote_revision` WHERE `quote_id` = %d', $quoteId)), 'the revisions of the shop are kept');
        self::assertSame(['1'], $this->column(\sprintf('SELECT COUNT(*) FROM `quote_revision` WHERE `quote_id` = %d AND `items_untaxed` IS NULL AND `items_tax` IS NULL AND `postage_tax` IS NULL AND `reverse_charge` IS NULL AND `valid_until` IS NULL AND `delivery_module_id` IS NULL', $quoteId)), 'and left with nothing frozen');

        $this->runTheBlock(self::BLOCK_TITLE);

        self::assertSame(self::$freshInstallShape, $this->shapeOfTheTables(), 'a second run changes nothing');
    }

    public function testTheCartsAndTheOrdersAShopAlreadyHasAreLeftWithNoQuote(): void
    {
        $titleId = (int) $this->connection()->query('SELECT MIN(`id`) FROM `customer_title`')->fetchColumn();
        $currencyId = (int) $this->connection()->query('SELECT MIN(`id`) FROM `currency`')->fetchColumn();
        $this->connection()->exec(\sprintf(
            "INSERT INTO `customer` (`title_id`, `customer_group_id`, `firstname`, `lastname`, `email`, `version`) VALUES (%d, %d, 'Old', 'Customer', 'migration-quote@test.com', 1)",
            $titleId,
            (int) $this->connection()->query('SELECT `id` FROM `customer_group` WHERE `is_default` = 1')->fetchColumn(),
        ));
        $customerId = (int) $this->connection()->query("SELECT `id` FROM `customer` WHERE `email` = 'migration-quote@test.com'")->fetchColumn();
        $this->connection()->exec(\sprintf("INSERT INTO `cart` (`token`, `customer_id`, `currency_id`) VALUES ('migration-quote-cart', %d, %d)", $customerId, $currencyId));

        try {
            $this->runTheBlock(self::BLOCK_TITLE);

            self::assertSame(['1'], $this->column("SELECT COUNT(*) FROM `cart` WHERE `token` = 'migration-quote-cart' AND `quote_id` IS NULL AND `quote_revision` IS NULL AND `set_aside_cart_id` IS NULL"));
            self::assertSame(['0'], $this->column('SELECT COUNT(*) FROM `cart_item` WHERE `quote_item_id` IS NOT NULL'));
            self::assertSame(['0'], $this->column('SELECT COUNT(*) FROM `order` WHERE `quote_id` IS NOT NULL'));
            self::assertSame(['0'], $this->column('SELECT COUNT(*) FROM `order_version` WHERE `quote_id` IS NOT NULL'));
        } finally {
            $this->connection()->exec('DELETE FROM `cart` WHERE `customer_id` = '.$customerId);
            $this->connection()->exec('DELETE FROM `customer` WHERE `id` = '.$customerId);
        }
    }

    public function testTheCompanyBlockGivesTheQuoteRightToTheAdministratorAndTheBuyerNotTheApprover(): void
    {
        $this->connection()->exec('DELETE FROM `company_role_i18n` WHERE `id` IN (SELECT `id` FROM `company_role` WHERE `company_id` IS NULL)');
        $this->connection()->exec('DELETE FROM `company_role` WHERE `company_id` IS NULL');

        $this->runTheBlock(self::COMPANY_BLOCK_TITLE);

        $permissions = $this->column('SELECT `code`, `permissions` FROM `company_role` WHERE `company_id` IS NULL ORDER BY `code`', true);

        self::assertSame(['approver', 'buyer', 'company_admin'], array_keys($permissions));
        self::assertContains('quote.request', json_decode($permissions['company_admin'], true, 512, \JSON_THROW_ON_ERROR));
        self::assertContains('quote.request', json_decode($permissions['buyer'], true, 512, \JSON_THROW_ON_ERROR));
        self::assertNotContains('quote.request', json_decode($permissions['approver'], true, 512, \JSON_THROW_ON_ERROR));

        // Run again, a replay leaves the roles as they are.
        $this->runTheBlock(self::COMPANY_BLOCK_TITLE);

        self::assertSame($permissions, $this->column('SELECT `code`, `permissions` FROM `company_role` WHERE `company_id` IS NULL ORDER BY `code`', true));
        self::assertSame(['3'], $this->column('SELECT COUNT(*) FROM `company_role` WHERE `company_id` IS NULL'));
    }

    /**
     * The fresh install seeds the rows under fixed ids: a replay gives them the next ones, and a
     * test that reads them by those ids would follow the order the cases ran in.
     */
    private function putTheSeedsBackUnderTheirIds(): void
    {
        $this->deleteTheSeeds();

        foreach (self::$freshInstallSeeds as $table => $rows) {
            foreach ($rows as $row) {
                $this->insertRow($table, $row);
            }
        }
    }

    private function putTheSharedRolesBack(): void
    {
        $this->connection()->exec('DELETE FROM `company_role_i18n` WHERE `id` IN (SELECT `id` FROM `company_role` WHERE `company_id` IS NULL)');
        $this->connection()->exec('DELETE FROM `company_role` WHERE `company_id` IS NULL');

        foreach (self::$freshInstallRoles as $table => $rows) {
            foreach ($rows as $row) {
                $this->insertRow($table, $row);
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insertRow(string $table, array $row): void
    {
        $statement = $this->connection()->prepare(\sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', array_keys($row)),
            implode(', ', array_fill(0, \count($row), '?')),
        ));
        $statement->execute(array_values($row));
    }

    private function deleteTheSeeds(): void
    {
        $this->connection()->exec("DELETE FROM `config_i18n` WHERE `id` IN (SELECT `id` FROM `config` WHERE `name` IN ('quote_mode', 'quote_validity_days', 'quote_ref_format'))");
        $this->connection()->exec("DELETE FROM `config` WHERE `name` IN ('quote_mode', 'quote_validity_days', 'quote_ref_format')");
        $this->connection()->exec("DELETE FROM `message_i18n` WHERE `id` IN (SELECT `id` FROM `message` WHERE `name` LIKE 'quote\\_%')");
        $this->connection()->exec("DELETE FROM `message` WHERE `name` LIKE 'quote\\_%'");
        $this->connection()->exec("DELETE FROM `resource_i18n` WHERE `id` IN (SELECT `id` FROM `resource` WHERE `code` = 'admin.quote')");
        $this->connection()->exec("DELETE FROM `resource` WHERE `code` = 'admin.quote'");
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function seeds(): array
    {
        $rows = static fn (ConnectionInterface $connection, string $sql): array => $connection->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'config' => $rows($this->connection(), "SELECT * FROM `config` WHERE `name` IN ('quote_mode', 'quote_validity_days', 'quote_ref_format') ORDER BY `name`"),
            'config_i18n' => $rows($this->connection(), "SELECT i.* FROM `config_i18n` i JOIN `config` c ON c.`id` = i.`id` WHERE c.`name` IN ('quote_mode', 'quote_validity_days', 'quote_ref_format') ORDER BY c.`name`, i.`locale`"),
            'message' => $rows($this->connection(), "SELECT * FROM `message` WHERE `name` LIKE 'quote\\_%' ORDER BY `name`"),
            'message_i18n' => $rows($this->connection(), "SELECT i.* FROM `message_i18n` i JOIN `message` m ON m.`id` = i.`id` WHERE m.`name` LIKE 'quote\\_%' ORDER BY m.`name`, i.`locale`"),
            'resource' => $rows($this->connection(), "SELECT * FROM `resource` WHERE `code` = 'admin.quote'"),
            'resource_i18n' => $rows($this->connection(), "SELECT i.* FROM `resource_i18n` i JOIN `resource` r ON r.`id` = i.`id` WHERE r.`code` = 'admin.quote' ORDER BY i.`locale`"),
        ];
    }

    /**
     * What a replay may not change: the rows, whatever ids and dates they were given.
     *
     * @param array<string, list<array<string, mixed>>> $seeds
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function withoutIds(array $seeds): array
    {
        return array_map(
            static fn (array $rows): array => array_map(
                static fn (array $row): array => array_diff_key($row, ['id' => true, 'created_at' => true, 'updated_at' => true]),
                $rows,
            ),
            $seeds,
        );
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function sharedRoles(): array
    {
        return [
            'company_role' => $this->connection()->query('SELECT * FROM `company_role` WHERE `company_id` IS NULL ORDER BY `id`')->fetchAll(\PDO::FETCH_ASSOC),
            'company_role_i18n' => $this->connection()->query('SELECT i.* FROM `company_role_i18n` i JOIN `company_role` r ON r.`id` = i.`id` WHERE r.`company_id` IS NULL ORDER BY i.`id`, i.`locale`')->fetchAll(\PDO::FETCH_ASSOC),
        ];
    }

    /**
     * Whatever state a failed case left behind: each piece is dropped only when it is there.
     */
    private function takeTheDatabaseBackToAShopWithoutQuotes(): void
    {
        $this->deleteTheSeeds();

        foreach ([
            'cart' => [['fk_cart_quote_id', 'fk_cart_set_aside_cart_id'], ['idx_cart_quote_id', 'idx_cart_set_aside_cart_id'], ['quote_id', 'quote_revision', 'set_aside_cart_id']],
            'cart_item' => [['fk_cart_item_quote_item_id'], ['idx_cart_item_quote_item_id'], ['quote_item_id']],
            'order' => [['fk_order_quote_id'], ['uk_order_quote_id'], ['quote_id']],
            'order_version' => [[], [], ['quote_id']],
        ] as $table => [$foreignKeys, $indexes, $columns]) {
            foreach ($foreignKeys as $foreignKey) {
                if ($this->exists('TABLE_CONSTRAINTS', $table, 'CONSTRAINT_NAME', $foreignKey)) {
                    $this->connection()->exec(\sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $table, $foreignKey));
                }
            }

            foreach ($indexes as $index) {
                if ($this->exists('STATISTICS', $table, 'INDEX_NAME', $index)) {
                    $this->connection()->exec(\sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $table, $index));
                }
            }

            foreach (array_reverse($columns) as $column) {
                if ($this->exists('COLUMNS', $table, 'COLUMN_NAME', $column)) {
                    $this->connection()->exec(\sprintf('ALTER TABLE `%s` DROP COLUMN `%s`', $table, $column));
                }
            }
        }

        foreach (self::NEW_TABLES as $table) {
            $this->connection()->exec(\sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
    }

    /**
     * The revision table without what freezes a revision, the choices of a draft and the copy of
     * the addresses: the table an earlier form of the block built.
     */
    private function putTheRevisionTableBackToItsEarlierForm(): void
    {
        $this->connection()->exec('DROP TABLE `quote_revision_address`');
        $this->connection()->exec('ALTER TABLE `quote_revision` DROP FOREIGN KEY `fk_quote_revision_delivery_module_id`');
        $this->connection()->exec('ALTER TABLE `quote_revision` DROP INDEX `idx_quote_revision_delivery_module_id`');

        foreach (['delivery_module_id', 'valid_until', 'reverse_charge', 'postage_tax', 'items_tax', 'items_untaxed'] as $column) {
            $this->connection()->exec(\sprintf('ALTER TABLE `quote_revision` DROP COLUMN `%s`', $column));
        }
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

    private function runTheBlock(string $title): void
    {
        foreach ($this->blockStatements($title) as $statement) {
            $this->connection()->exec($statement);
        }
    }

    /**
     * The statements of the block, read from the script an update runs.
     *
     * @return list<string>
     */
    private function blockStatements(string $title): array
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.self::PENDING_SCRIPT);
        $start = strpos($script, $title);
        self::assertIsInt($start, \sprintf('The pending update script has no "%s" block.', $title));

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
     * Columns in their order, indexes and foreign keys of every table the block builds or changes.
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
                    static fn (array $foreignKey): array => [
                        ...$foreignKey,
                        'DELETE_RULE' => 'NO ACTION' === $foreignKey['DELETE_RULE'] ? 'RESTRICT' : $foreignKey['DELETE_RULE'],
                        'UPDATE_RULE' => 'NO ACTION' === $foreignKey['UPDATE_RULE'] ? 'RESTRICT' : $foreignKey['UPDATE_RULE'],
                    ],
                    $foreignKeys->fetchAll(\PDO::FETCH_ASSOC),
                ),
            ];
        }

        return $shape;
    }

    /**
     * @return ($pairs is true ? array<string, string> : list<string>)
     */
    private function column(string $sql, bool $pairs = false): array
    {
        $rows = $this->connection()->query($sql)->fetchAll(\PDO::FETCH_NUM);

        if ($pairs) {
            return array_column($rows, 1, 0);
        }

        return array_map(static fn (array $row): string => (string) $row[0], $rows);
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getConnection('TheliaMain');
    }
}
