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

namespace Thelia\Tests\Integration\Company;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Test\IntegrationTestCase;

/**
 * The block of the pending update script that gives a shop the company accounts.
 *
 * Each case takes the database back to the shape of a shop with no company, runs the block read
 * from the shipped script, and compares what it built with the fresh install it was taken from.
 * A schema change commits on its own, so no transaction wraps the case; whatever happens, the
 * block is run again afterwards to put the fresh install shape back.
 */
final class CompanyMigrationTest extends IntegrationTestCase
{
    /** The pending script, the newest one: it moves on at each release. */
    private const PENDING_SCRIPT = '3.5.0.sql';

    private const BLOCK_TITLE = '-- Company accounts';

    /** Created by the block, children first. */
    private const NEW_TABLES = ['company_invitation_use', 'company_customer', 'company_role_i18n', 'company_role', 'company'];

    /** Changed by the block, whose whole shape is compared. */
    private const ALTERED_TABLES = ['address', 'order', 'order_version'];

    protected bool $useTransaction = false;

    /** What the fresh install seeds, which the block must give a shop that updates. */
    private const SHIPPED_ROLES = [
        'approver' => '["order.view_own","order.view_company","order.approve"]',
        'buyer' => '["order.place","order.view_own"]',
        'company_admin' => '["order.place","order.view_own","order.view_company","address.manage_shared","buyer.manage","company.edit"]',
    ];

    /**
     * Read from the database by the first case of the process, before any case has run the block
     * again: a later case would otherwise take the shape its predecessor left as the fresh one.
     *
     * @var array<string, array<string, mixed>>|null
     */
    private static ?array $freshInstallShape = null;

    /** @var array<string, mixed>|false|null */
    private static array|false|null $freshInstallResource = null;

    /** @var list<array<string, mixed>>|null */
    private static ?array $freshInstallResourceTitles = null;

    protected function setUp(): void
    {
        parent::setUp();

        self::$freshInstallShape ??= $this->shapeOfTheTables();
        self::$freshInstallResource ??= $this->connection()->query("SELECT * FROM `resource` WHERE `code` = 'admin.company'")->fetch(\PDO::FETCH_ASSOC);
        self::$freshInstallResourceTitles ??= $this->connection()->query("SELECT i.* FROM `resource_i18n` i JOIN `resource` r ON r.`id` = i.`id` WHERE r.`code` = 'admin.company'")->fetchAll(\PDO::FETCH_ASSOC);

        $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->takeTheDatabaseBackToAShopWithoutCompanies();
    }

    protected function tearDown(): void
    {
        try {
            $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 0');
            $this->takeTheDatabaseBackToAShopWithoutCompanies();
            $this->runTheBlock();
            $this->putTheResourceBackUnderItsId();
        } finally {
            $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        parent::tearDown();
    }

    public function testTheBlockBuildsWhatTheFreshInstallHolds(): void
    {
        $this->runTheBlock();

        self::assertSame(self::$freshInstallShape, $this->shapeOfTheTables());
    }

    public function testTheBlockCanBeRunTwiceWithoutErrorAndLeavesTheSameShape(): void
    {
        $this->runTheBlock();
        $this->runTheBlock();

        self::assertSame(self::$freshInstallShape, $this->shapeOfTheTables());
        self::assertSame(['3'], $this->column('SELECT COUNT(*) FROM `company_role` WHERE `company_id` IS NULL'));
        self::assertSame(['24'], $this->column('SELECT COUNT(*) FROM `company_role_i18n`'));
        self::assertSame(['1'], $this->column("SELECT COUNT(*) FROM `resource` WHERE `code` = 'admin.company'"));
        self::assertSame(['8'], $this->column("SELECT COUNT(*) FROM `resource_i18n` i JOIN `resource` r ON r.`id` = i.`id` WHERE r.`code` = 'admin.company'"));
    }

    public function testTheShippedRolesAreTheOnesTheFreshInstallSeeds(): void
    {
        $this->runTheBlock();

        self::assertSame(self::SHIPPED_ROLES, $this->shippedRoles());
        self::assertSame(
            ['company_admin' => 'Company administrator', 'buyer' => 'Buyer', 'approver' => 'Approver'],
            $this->column("SELECT r.`code`, i.`title` FROM `company_role` r JOIN `company_role_i18n` i ON i.`id` = r.`id` AND i.`locale` = 'en_US' WHERE r.`company_id` IS NULL ORDER BY r.`id`", true),
        );
    }

    public function testAReplayKeepsTheRoleAnAdministratorEditedAndTheRowsOfTheShop(): void
    {
        $this->runTheBlock();

        $this->connection()->exec("UPDATE `company_role` SET `permissions` = '[\"order.place\"]' WHERE `code` = 'approver' AND `company_id` IS NULL");
        $this->connection()->exec("INSERT INTO `company` (`name`, `active`) VALUES ('Acme', 1)");
        $companyId = (int) $this->connection()->query("SELECT `id` FROM `company` WHERE `name` = 'Acme'")->fetchColumn();
        $this->connection()->exec(\sprintf("INSERT INTO `company_role` (`code`, `company_id`, `permissions`) VALUES ('buyer', %d, '[\"order.place\"]')", $companyId));

        $this->runTheBlock();

        self::assertSame(['["order.place"]'], $this->column("SELECT `permissions` FROM `company_role` WHERE `code` = 'approver' AND `company_id` IS NULL"), 'A replay does not put the shipped permissions back.');
        self::assertSame(['3'], $this->column('SELECT COUNT(*) FROM `company_role` WHERE `company_id` IS NULL'));
        self::assertSame(['1'], $this->column(\sprintf('SELECT COUNT(*) FROM `company_role` WHERE `company_id` = %d', $companyId)), 'A role of a company with the code of a shipped one is not taken for it.');
        self::assertSame(['1'], $this->column('SELECT COUNT(*) FROM `company`'));
        self::assertSame(self::$freshInstallShape, $this->shapeOfTheTables());

        $this->connection()->exec('DELETE FROM `company_role` WHERE `company_id` = '.$companyId);
        $this->connection()->exec('DELETE FROM `company` WHERE `id` = '.$companyId);
    }

    public function testAShopThatLostASharedRoleGetsItBackAndNoOtherOne(): void
    {
        $this->runTheBlock();
        $this->connection()->exec("DELETE FROM `company_role` WHERE `code` = 'buyer' AND `company_id` IS NULL");

        $this->runTheBlock();

        self::assertSame(['approver', 'buyer', 'company_admin'], $this->column('SELECT `code` FROM `company_role` WHERE `company_id` IS NULL ORDER BY `code`'));
    }

    public function testTheOrdersAndTheAddressesAShopAlreadyHasAreLeftWithNoCompany(): void
    {
        $titleId = (int) $this->connection()->query('SELECT MIN(`id`) FROM `customer_title`')->fetchColumn();
        $countryId = (int) $this->connection()->query('SELECT MIN(`id`) FROM `country`')->fetchColumn();
        $this->connection()->exec(\sprintf(
            "INSERT INTO `customer` (`title_id`, `customer_group_id`, `firstname`, `lastname`, `email`, `version`) VALUES (%d, %d, 'Old', 'Customer', 'migration-company@test.com', 1)",
            $titleId,
            (int) $this->connection()->query('SELECT `id` FROM `customer_group` WHERE `is_default` = 1')->fetchColumn(),
        ));
        $customerId = (int) $this->connection()->query("SELECT `id` FROM `customer` WHERE `email` = 'migration-company@test.com'")->fetchColumn();
        $this->connection()->exec(\sprintf(
            "INSERT INTO `address` (`customer_id`, `title_id`, `firstname`, `lastname`, `address1`, `address2`, `address3`, `zipcode`, `city`, `country_id`) VALUES (%d, %d, 'Old', 'Customer', '1 rue', '', '', '75001', 'Paris', %d)",
            $customerId,
            $titleId,
            $countryId,
        ));

        try {
            $this->runTheBlock();

            self::assertSame(['1'], $this->column('SELECT COUNT(*) FROM `address` WHERE `customer_id` = '.$customerId.' AND `company_id` IS NULL'));
            self::assertSame(['0'], $this->column('SELECT COUNT(*) FROM `order` WHERE `company_id` IS NOT NULL OR `placed_by_customer_id` IS NOT NULL'));
        } finally {
            $this->connection()->exec('DELETE FROM `address` WHERE `customer_id` = '.$customerId);
            $this->connection()->exec('DELETE FROM `customer` WHERE `id` = '.$customerId);
        }
    }

    /**
     * The fresh install seeds the resource under a fixed id: a replay gives it the next one, and a
     * test that reads it by that id would follow the order the cases ran in.
     */
    private function putTheResourceBackUnderItsId(): void
    {
        if (false === self::$freshInstallResource) {
            return;
        }

        $this->deleteTheResource();

        foreach ([['resource', [self::$freshInstallResource]], ['resource_i18n', self::$freshInstallResourceTitles]] as [$table, $rows]) {
            foreach ($rows as $row) {
                $statement = $this->connection()->prepare(\sprintf(
                    'INSERT INTO `%s` (`%s`) VALUES (%s)',
                    $table,
                    implode('`, `', array_keys($row)),
                    implode(', ', array_fill(0, \count($row), '?')),
                ));
                $statement->execute(array_values($row));
            }
        }
    }

    private function deleteTheResource(): void
    {
        $this->connection()->exec("DELETE FROM `resource_i18n` WHERE `id` IN (SELECT `id` FROM `resource` WHERE `code` = 'admin.company')");
        $this->connection()->exec("DELETE FROM `resource` WHERE `code` = 'admin.company'");
    }

    /**
     * @return array<string, string>
     */
    private function shippedRoles(): array
    {
        return $this->column('SELECT `code`, `permissions` FROM `company_role` WHERE `company_id` IS NULL ORDER BY `code`', true);
    }

    /**
     * Whatever state a failed case left behind: each piece is dropped only when it is there.
     */
    private function takeTheDatabaseBackToAShopWithoutCompanies(): void
    {
        $this->deleteTheResource();

        foreach ([
            'address' => [['fk_address_company_id'], ['idx_address_company_id'], ['company_id']],
            'order' => [['fk_order_company_id', 'fk_order_placed_by_customer_id'], ['idx_order_company_id', 'idx_order_placed_by_customer_id'], ['company_id', 'placed_by_customer_id']],
            'order_version' => [[], [], ['company_id', 'placed_by_customer_id', 'placed_by_customer_id_version']],
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
        self::assertIsInt($start, 'The pending update script gives no company accounts.');

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
