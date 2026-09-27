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

namespace Thelia\Tests\Integration\Command;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Model\Map\ConfigTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * A shop upgraded from Thelia 2 keeps its tables in utf8 (utf8mb3), created by a MySQL whose
 * row format was still COMPACT, while the connection talks utf8mb4: the database refuses an
 * emoji with error 1366 where a fresh install stores it.
 *
 * Each case creates tables in that shape next to the schema of the test database, runs the
 * command on them only (--table), and drops them. A schema change commits on its own, so no
 * transaction wraps the cases.
 */
final class DatabaseConvertUtf8mb4CommandTest extends IntegrationTestCase
{
    private const TABLE = 'utf8mb4_conversion_note';
    private const FAILING_TABLE = 'utf8mb4_conversion_note_colliding';
    private const LAST_TABLE = 'utf8mb4_conversion_note_last';
    private const PARENT_TABLE = 'utf8mb4_conversion_parent';
    private const LATIN1_TABLE = 'utf8mb4_conversion_latin1';

    private const EMOJI_TITLE = 'Gift wrapping 🎁';

    protected bool $useTransaction = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropFixtureTables();
        $this->createUpgradedTable(self::TABLE);
    }

    protected function tearDown(): void
    {
        $this->dropFixtureTables();

        parent::tearDown();
    }

    public function testTheDryRunListsTheTableAndChangesNothing(): void
    {
        $tester = $this->runCommand([]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $display = $this->flatten($tester->getDisplay());
        self::assertStringContainsString(self::TABLE.' (utf8mb3_general_ci', $display);
        self::assertStringContainsString('row format Compact -> DYNAMIC', $display);
        self::assertStringContainsString('title (utf8mb3), body (utf8mb3)', $display);
        self::assertStringContainsString('Dry run, nothing was changed', $display);

        self::assertSame('utf8mb3_general_ci', $this->tableCollation(self::TABLE));
        self::assertSame(1366, $this->errorCodeOfAnEmojiInsert(self::TABLE));
    }

    public function testTheTableIsConvertedAndAnEmojiIsThenStored(): void
    {
        self::assertSame(1366, $this->errorCodeOfAnEmojiInsert(self::TABLE), 'The fixture must refuse an emoji before the conversion.');

        $tester = $this->runCommand(['--force' => true, '--table' => [self::TABLE]]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame('utf8mb4_general_ci', $this->tableCollation(self::TABLE));
        self::assertSame(
            [
                'title' => ['utf8mb4', 'varchar(255)'],
                'body' => ['utf8mb4', 'text'],
            ],
            $this->characterColumns(self::TABLE),
            'Every column must be in utf8mb4, and TEXT must stay TEXT as on a fresh install.',
        );
        self::assertSame('Dynamic', $this->rowFormat(self::TABLE), 'The unique key on VARCHAR(255) needs more than the 767 bytes of COMPACT.');

        self::assertSame(0, $this->errorCodeOfAnEmojiInsert(self::TABLE));
        self::assertSame(
            [self::EMOJI_TITLE, 'Wrapped with care ✨'],
            $this->connection()->query('SELECT title, body FROM `'.self::TABLE.'`')->fetch(\PDO::FETCH_NUM),
        );
    }

    public function testASecondRunFindsNothingLeftAndChangesNothing(): void
    {
        $this->runCommand(['--force' => true, '--table' => [self::TABLE]]);
        $definitionAfterTheFirstRun = $this->createStatement(self::TABLE);

        $tester = $this->runCommand(['--force' => true, '--table' => [self::TABLE]]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('already in utf8mb4', $tester->getDisplay());
        self::assertSame($definitionAfterTheFirstRun, $this->createStatement(self::TABLE));
    }

    public function testItStopsAtTheFirstTableThatFailsAndLeavesTheNextOnesUntouched(): void
    {
        // utf8mb3_bin tells "A" from "a"; utf8mb4_general_ci does not, so the unique key
        // collides while the table is rebuilt.
        $this->connection()->exec('CREATE TABLE `'.self::FAILING_TABLE.'` (
            `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
            UNIQUE KEY `code_unique` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci');
        $this->connection()->exec('INSERT INTO `'.self::FAILING_TABLE."` (`code`) VALUES ('A'), ('a')");
        $this->createUpgradedTable(self::LAST_TABLE);

        $tester = $this->runCommand([
            '--force' => true,
            '--table' => [self::TABLE, self::FAILING_TABLE, self::LAST_TABLE],
        ]);

        self::assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
        $display = $this->flatten($tester->getDisplay());
        self::assertStringContainsString('code (utf8mb3_bin -> utf8mb4_general_ci)', $display);
        self::assertStringContainsString('Converting table "'.self::FAILING_TABLE.'" failed', $display);
        self::assertStringContainsString('Duplicate entry', $display);
        self::assertSame('utf8mb4_general_ci', $this->tableCollation(self::TABLE));
        self::assertSame('utf8mb3_general_ci', $this->tableCollation(self::FAILING_TABLE));
        self::assertSame('utf8mb3_general_ci', $this->tableCollation(self::LAST_TABLE));
    }

    public function testATextColumnUnderAForeignKeyIsReportedAndNothingIsConverted(): void
    {
        $this->connection()->exec('CREATE TABLE `'.self::PARENT_TABLE.'` (
            `code` VARCHAR(10) NOT NULL PRIMARY KEY
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci');
        $this->connection()->exec('ALTER TABLE `'.self::TABLE.'` ADD `parent_code` VARCHAR(10) NULL,
            ADD CONSTRAINT `fk_utf8mb4_conversion_parent` FOREIGN KEY (`parent_code`) REFERENCES `'.self::PARENT_TABLE.'` (`code`)');

        $tester = $this->runCommand(['--force' => true, '--table' => [self::TABLE, self::PARENT_TABLE]]);

        self::assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('belongs to the foreign key "fk_utf8mb4_conversion_parent"', $this->flatten($tester->getDisplay()));
        self::assertSame('utf8mb3_general_ci', $this->tableCollation(self::TABLE));
        self::assertSame('utf8mb3_general_ci', $this->tableCollation(self::PARENT_TABLE));
    }

    public function testALatin1TableIsReportedAndLeftAlone(): void
    {
        $this->connection()->exec('CREATE TABLE `'.self::LATIN1_TABLE.'` (
            `id` INTEGER NOT NULL PRIMARY KEY,
            `title` VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci');

        $tester = $this->runCommand(['--force' => true, '--table' => [self::LATIN1_TABLE]]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString(self::LATIN1_TABLE.' (latin1)', $this->flatten($tester->getDisplay()));
        self::assertSame('latin1_swedish_ci', $this->tableCollation(self::LATIN1_TABLE));
    }

    public function testAnUnknownTableIsRefused(): void
    {
        $tester = $this->runCommand(['--force' => true, '--table' => ['utf8mb4_conversion_missing']]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('No table named "utf8mb4_conversion_missing"', $tester->getDisplay());
    }

    /**
     * The shape Thelia 2 created: utf8 tables, no row format named (COMPACT on the MySQL of the
     * time), a unique key on a VARCHAR(255) and a TEXT column.
     */
    private function createUpgradedTable(string $table): void
    {
        $this->connection()->exec('CREATE TABLE `'.$table.'` (
            `id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `body` TEXT NULL,
            UNIQUE KEY `title_unique` (`title`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=COMPACT');
    }

    private function dropFixtureTables(): void
    {
        $this->connection()->exec(\sprintf(
            'DROP TABLE IF EXISTS `%s`, `%s`, `%s`, `%s`, `%s`',
            self::TABLE,
            self::FAILING_TABLE,
            self::LAST_TABLE,
            self::PARENT_TABLE,
            self::LATIN1_TABLE,
        ));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function runCommand(array $arguments): CommandTester
    {
        $tester = new CommandTester(
            (new Application(self::$kernel))->find('thelia:database:convert-utf8mb4'),
        );
        $tester->execute($arguments, ['interactive' => false]);

        return $tester;
    }

    /**
     * @return int the MySQL error code, 0 when the row was written
     */
    private function errorCodeOfAnEmojiInsert(string $table): int
    {
        $statement = $this->connection()->prepare('INSERT INTO `'.$table.'` (title, body) VALUES (:title, :body)');

        try {
            $statement->execute(['title' => self::EMOJI_TITLE, 'body' => 'Wrapped with care ✨']);
        } catch (\PDOException $exception) {
            return (int) ($exception->errorInfo[1] ?? -1);
        }

        return 0;
    }

    private function tableCollation(string $table): string
    {
        return (string) $this->schemaValue('SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', $table);
    }

    private function rowFormat(string $table): string
    {
        return (string) $this->schemaValue('SELECT ROW_FORMAT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', $table);
    }

    /**
     * @return array<string, array{string, string}>
     */
    private function characterColumns(string $table): array
    {
        $statement = $this->connection()->prepare(
            'SELECT COLUMN_NAME AS column_name, CHARACTER_SET_NAME AS charset, COLUMN_TYPE AS column_type
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CHARACTER_SET_NAME IS NOT NULL
             ORDER BY ORDINAL_POSITION',
        );
        $statement->execute(['table' => $table]);

        $columns = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $columns[(string) $row['column_name']] = [(string) $row['charset'], strtolower((string) $row['column_type'])];
        }

        return $columns;
    }

    private function createStatement(string $table): string
    {
        return (string) $this->connection()->query('SHOW CREATE TABLE `'.$table.'`')->fetch(\PDO::FETCH_NUM)[1];
    }

    private function schemaValue(string $sql, string $table): mixed
    {
        $statement = $this->connection()->prepare($sql);
        $statement->execute(['table' => $table]);

        return $statement->fetchColumn();
    }

    /**
     * SymfonyStyle wraps long lines at the terminal width.
     */
    private function flatten(string $display): string
    {
        return (string) preg_replace('/\s+/', ' ', $display);
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getWriteConnection(ConfigTableMap::DATABASE_NAME);
    }
}
