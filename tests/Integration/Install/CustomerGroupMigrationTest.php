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
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The block of the pending update script that gives a shop the customer groups.
 *
 * Each case takes the database back to the shape of a shop with no group, puts
 * customers in it, runs the block read from the shipped script, and compares what
 * it built with the fresh install it was taken from. `customer.customer_group_id`
 * is NOT NULL, so the block creates it nullable, fills it, then makes it required:
 * a customer the shop already has must end up in the default group, not make the
 * update fail. A schema change commits on its own, so no transaction wraps the
 * case; whatever happens, the block is run again afterwards to put the fresh
 * install shape back.
 */
final class CustomerGroupMigrationTest extends IntegrationTestCase
{
    /** The pending script, the one UpdateScriptTest names: it moves on at each release. */
    private const PENDING_SCRIPT = '3.3.0.sql';

    private const BLOCK_TITLE = '-- Customer groups';

    private const CUSTOMER_EMAIL_PREFIX = 'migration-customer-group-';

    /** Created by the block, children first. */
    private const NEW_TABLES = [
        'customer_group_product_price',
        'customer_group_payment_module',
        'customer_group_category',
        'sale_customer_group',
        'catalog_price_rule_customer_group',
        'customer_group_i18n',
        'customer_group',
    ];

    /** Changed by the block, whose whole shape is compared. */
    private const ALTERED_TABLES = ['customer', 'customer_version', 'order', 'order_version'];

    protected bool $useTransaction = false;

    /** @var array<string, array<string, mixed>> */
    private array $freshInstallShape = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->freshInstallShape = $this->shapeOfTheTables();

        $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->takeTheDatabaseBackToAShopWithoutGroups();
    }

    protected function tearDown(): void
    {
        $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->connection()->exec(\sprintf("DELETE FROM `customer` WHERE `email` LIKE '%s%%'", self::CUSTOMER_EMAIL_PREFIX));
        // The fresh install shape and its seed, not the one a case left (a renamed group, another group).
        $this->takeTheDatabaseBackToAShopWithoutGroups();
        $this->runTheBlock();
        $this->connection()->exec('SET FOREIGN_KEY_CHECKS = 1');

        CustomerTableMap::clearInstancePool();

        parent::tearDown();
    }

    public function testTheBlockBuildsWhatTheFreshInstallHolds(): void
    {
        $this->runTheBlock();

        self::assertSame($this->freshInstallShape, $this->shapeOfTheTables());
    }

    public function testAPriceOfAGroupIsTypedInItsCurrencyUnlessTheRowSaysOtherwise(): void
    {
        $this->runTheBlock();

        self::assertSame(
            ['0'],
            $this->column("SELECT `COLUMN_DEFAULT` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer_group_product_price' AND `COLUMN_NAME` = 'from_default_currency'"),
            'The fresh table is not derived from the default currency by default.',
        );
    }

    public function testEveryExistingCustomerJoinsTheDefaultGroupAndItsVersionsToo(): void
    {
        $this->insertACustomerWithItsVersion('a');
        $this->insertACustomerWithItsVersion('b');

        $this->runTheBlock();

        $defaultGroupId = $this->theDefaultGroupId();

        self::assertSame(
            [(string) $defaultGroupId, (string) $defaultGroupId],
            $this->column("SELECT `customer_group_id` FROM `customer` WHERE `email` LIKE '".self::CUSTOMER_EMAIL_PREFIX."%' ORDER BY `email`"),
        );
        self::assertSame(
            [(string) $defaultGroupId, (string) $defaultGroupId],
            $this->column("SELECT v.`customer_group_id` FROM `customer_version` v JOIN `customer` c ON c.`id` = v.`id` WHERE c.`email` LIKE '".self::CUSTOMER_EMAIL_PREFIX."%' ORDER BY c.`email`"),
        );
    }

    public function testTheDefaultGroupIsNamedInEveryLanguageTheFreshInstallSeeds(): void
    {
        $this->runTheBlock();

        $defaultGroupId = $this->theDefaultGroupId();
        $seeded = $this->column('SELECT `locale` FROM `customer_group_i18n` WHERE `id` = '.$defaultGroupId.' ORDER BY `locale`');

        self::assertSame(['cs_CZ', 'de_DE', 'en_US', 'es_ES', 'fr_FR', 'it_IT', 'nl_NL', 'ru_RU'], $seeded);
        self::assertSame(
            ['Default customer group'],
            $this->column('SELECT `title` FROM `customer_group_i18n` WHERE `id` = '.$defaultGroupId." AND `locale` = 'en_US'"),
        );
    }

    public function testTheBlockGrantsTheResourceInEveryLanguage(): void
    {
        $this->runTheBlock();

        self::assertSame(
            ['8'],
            $this->column("SELECT COUNT(*) FROM `resource_i18n` i JOIN `resource` r ON r.`id` = i.`id` WHERE r.`code` = 'admin.customer.group'"),
        );
    }

    public function testTheBlockCanBeReplayedWithoutAddingAGroupOrMovingACustomer(): void
    {
        $this->insertACustomerWithItsVersion('a');

        $this->runTheBlock();

        // The administrator renamed the default group and added another one in the meantime.
        $defaultGroupId = $this->theDefaultGroupId();
        $this->connection()->exec("UPDATE `customer_group` SET `code` = 'retail' WHERE `id` = ".$defaultGroupId);
        $this->connection()->exec("INSERT INTO `customer_group` (`code`, `position`, `is_default`, `discount`, `display_taxed_price`) VALUES ('pro', 2, 0, 10, 0)");
        $proGroupId = (int) $this->connection()->query("SELECT `id` FROM `customer_group` WHERE `code` = 'pro'")->fetchColumn();
        $this->connection()->exec('UPDATE `customer` SET `customer_group_id` = '.$proGroupId." WHERE `email` LIKE '".self::CUSTOMER_EMAIL_PREFIX."%'");

        $this->runTheBlock();

        self::assertSame(['retail'], $this->column('SELECT `code` FROM `customer_group` WHERE `is_default` = 1'));
        self::assertSame(['2'], $this->column('SELECT COUNT(*) FROM `customer_group`'));
        self::assertSame([(string) $proGroupId], $this->column("SELECT `customer_group_id` FROM `customer` WHERE `email` LIKE '".self::CUSTOMER_EMAIL_PREFIX."%'"));
        self::assertSame(['8'], $this->column('SELECT COUNT(*) FROM `customer_group_i18n` WHERE `id` = '.$defaultGroupId));
        self::assertSame(['1'], $this->column("SELECT COUNT(*) FROM `resource` WHERE `code` = 'admin.customer.group'"));
        self::assertSame($this->freshInstallShape, $this->shapeOfTheTables());
    }

    public function testAGroupThatAlreadyListsSomethingIsFlaggedRestrictedOnceAndANewOneIsNot(): void
    {
        // The shape the table had before the flags: the lists exist, the columns do not.
        $this->runTheBlock();
        $this->connection()->exec('ALTER TABLE `customer_group` DROP COLUMN `restricts_categories`, DROP COLUMN `restricts_payment_modules`');
        $this->connection()->exec("INSERT INTO `customer_group` (`code`, `position`, `is_default`, `discount`, `display_taxed_price`) VALUES ('pro', 2, 0, 0, 1), ('categories-only', 3, 0, 0, 1), ('open', 4, 0, 0, 1)");
        $pro = $this->groupId('pro');
        $categoriesOnly = $this->groupId('categories-only');
        // Foreign keys are off for the case: the ids are those of rows the shop had.
        $this->connection()->exec(\sprintf('INSERT INTO `customer_group_category` (`customer_group_id`, `category_id`) VALUES (%d, 9001), (%d, 9001)', $pro, $categoriesOnly));
        $this->connection()->exec(\sprintf('INSERT INTO `customer_group_payment_module` (`customer_group_id`, `module_id`) VALUES (%d, 9002)', $pro));

        $this->runTheBlock();

        self::assertSame(['1', '1'], $this->flagsOf('pro'));
        self::assertSame(['1', '0'], $this->flagsOf('categories-only'));
        self::assertSame(['0', '0'], $this->flagsOf('open'));
        self::assertSame(['0', '0'], $this->flagsOf('default'));
        self::assertSame($this->freshInstallShape, $this->shapeOfTheTables());

        // The category and the module of the professionals are deleted, then the update is replayed.
        $this->connection()->exec(\sprintf('DELETE FROM `customer_group_category` WHERE `customer_group_id` = %d', $pro));
        $this->connection()->exec(\sprintf('DELETE FROM `customer_group_payment_module` WHERE `customer_group_id` = %d', $pro));
        $this->runTheBlock();

        self::assertSame(['1', '1'], $this->flagsOf('pro'), 'A replay does not derive the flags from the lists again.');
    }

    public function testReplayingTheBlockDoesNotOpenAGroupWhoseListWasEmptiedByADeletion(): void
    {
        $this->runTheBlock();
        $this->connection()->exec("INSERT INTO `customer_group` (`code`, `position`, `is_default`, `discount`, `display_taxed_price`, `restricts_categories`, `restricts_payment_modules`) VALUES ('pro', 2, 0, 0, 1, 1, 1)");

        // Its categories and modules were deleted: the lists are empty, the flags say restricted.
        $this->runTheBlock();

        self::assertSame(['1', '1'], $this->flagsOf('pro'));
    }

    public function testAGroupTableWithoutARegistrationTokenGainsTheColumnAndItsUniqueIndexOnceAndAReplayKeepsTheTokens(): void
    {
        // The shape the table had before the registration link: the groups exist, the column and its index do not.
        $this->runTheBlock();
        $this->connection()->exec('ALTER TABLE `customer_group` DROP INDEX `customer_group_registration_token_unique`, DROP COLUMN `registration_token`');
        $this->connection()->exec("INSERT INTO `customer_group` (`code`, `position`, `is_default`, `discount`, `display_taxed_price`) VALUES ('pro', 2, 0, 0, 1), ('trade', 3, 0, 0, 1)");

        $this->runTheBlock();

        self::assertSame(['1'], $this->column("SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer_group' AND `COLUMN_NAME` = 'registration_token' AND `COLUMN_TYPE` = 'varchar(64)' AND `IS_NULLABLE` = 'YES'"));
        self::assertSame(['0'], $this->column("SELECT `NON_UNIQUE` FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer_group' AND `INDEX_NAME` = 'customer_group_registration_token_unique'"));
        self::assertSame(['3'], $this->column('SELECT COUNT(*) FROM `customer_group` WHERE `registration_token` IS NULL'), 'Every group starts with a closed link.');
        self::assertSame($this->freshInstallShape, $this->shapeOfTheTables());

        // The administrator opens the link of a group, then the update is replayed.
        $token = str_repeat('ab', 32);
        $this->connection()->exec(\sprintf("UPDATE `customer_group` SET `registration_token` = '%s' WHERE `code` = 'pro'", $token));
        $this->runTheBlock();

        self::assertSame([$token], $this->column("SELECT `registration_token` FROM `customer_group` WHERE `code` = 'pro'"), 'A replay keeps the token.');
        self::assertSame($this->freshInstallShape, $this->shapeOfTheTables());
    }

    public function testTwoGroupsCannotHoldTheSameRegistrationToken(): void
    {
        $this->runTheBlock();
        $token = str_repeat('cd', 32);
        $this->connection()->exec(\sprintf("INSERT INTO `customer_group` (`code`, `position`, `is_default`, `registration_token`) VALUES ('first', 2, 0, '%s')", $token));

        $this->expectException(\Throwable::class);
        $this->connection()->exec(\sprintf("INSERT INTO `customer_group` (`code`, `position`, `is_default`, `registration_token`) VALUES ('second', 3, 0, '%s')", $token));
    }

    /**
     * @return list<string> restricts_categories, restricts_payment_modules
     */
    private function flagsOf(string $code): array
    {
        $row = $this->connection()->query(\sprintf("SELECT `restricts_categories`, `restricts_payment_modules` FROM `customer_group` WHERE `code` = '%s'", $code))->fetch(\PDO::FETCH_NUM);

        return array_map('strval', $row);
    }

    private function groupId(string $code): int
    {
        return (int) $this->connection()->query(\sprintf("SELECT `id` FROM `customer_group` WHERE `code` = '%s'", $code))->fetchColumn();
    }

    /**
     * Whatever state a failed case left behind: each piece is dropped only when it is there.
     */
    private function takeTheDatabaseBackToAShopWithoutGroups(): void
    {
        $this->connection()->exec("DELETE FROM `resource` WHERE `code` = 'admin.customer.group'");

        foreach ([
            'customer' => ['fk_customer_customer_group_id', 'idx_customer_customer_group_id', ['customer_group_id']],
            'order' => ['fk_order_customer_group_id', 'fi_order_customer_group_id', ['customer_group_id', 'customer_group_discount_rate', 'customer_group_display_taxed_price']],
            'customer_version' => [null, null, ['customer_group_id']],
            'order_version' => [null, null, ['customer_group_id', 'customer_group_discount_rate', 'customer_group_display_taxed_price']],
        ] as $table => [$foreignKey, $index, $columns]) {
            if (null !== $foreignKey && $this->exists('TABLE_CONSTRAINTS', $table, 'CONSTRAINT_NAME', $foreignKey)) {
                $this->connection()->exec(\sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $table, $foreignKey));
            }

            if (null !== $index && $this->exists('STATISTICS', $table, 'INDEX_NAME', $index)) {
                $this->connection()->exec(\sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $table, $index));
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

    private function insertACustomerWithItsVersion(string $suffix): void
    {
        $titleId = (int) $this->connection()->query('SELECT MIN(`id`) FROM `customer_title`')->fetchColumn();
        $email = self::CUSTOMER_EMAIL_PREFIX.$suffix.'@test.com';

        $this->connection()->exec(\sprintf(
            "INSERT INTO `customer` (`title_id`, `firstname`, `lastname`, `email`, `version`) VALUES (%d, 'Old', 'Customer', '%s', 1)",
            $titleId,
            $email,
        ));
        $customerId = (int) $this->connection()->query(\sprintf("SELECT `id` FROM `customer` WHERE `email` = '%s'", $email))->fetchColumn();
        $this->connection()->exec(\sprintf(
            "INSERT INTO `customer_version` (`id`, `title_id`, `firstname`, `lastname`, `email`, `version`) VALUES (%d, %d, 'Old', 'Customer', '%s', 1)",
            $customerId,
            $titleId,
            $email,
        ));
    }

    private function theDefaultGroupId(): int
    {
        $ids = $this->column('SELECT `id` FROM `customer_group` WHERE `is_default` = 1');

        self::assertCount(1, $ids, 'exactly one default group');

        return (int) $ids[0];
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
        self::assertIsInt($start, 'The pending update script gives no customer groups.');

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
                    static fn (array $foreignKey): array => str_replace('NO ACTION', 'RESTRICT', $foreignKey),
                    $foreignKeys->fetchAll(\PDO::FETCH_ASSOC),
                ),
            ];
        }

        return $shape;
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
        return Propel::getWriteConnection(CustomerTableMap::DATABASE_NAME);
    }
}
