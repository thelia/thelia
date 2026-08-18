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
use Thelia\Core\Install\Database;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The backup taken before an update is the only thing that can undo a schema migration,
 * so it has to be restorable. It was not: every value went through addslashes() and was
 * written between double quotes, which turned NULL into an empty string. The restore
 * then died on the first nullable non-string column it met — and because a dump is a
 * DROP, a CREATE and the rows of one table after another, it died having already
 * rebuilt the tables before that one and emptied the one it was on.
 *
 * A dump is worth nothing unless what comes back is what went in, so the round trip is
 * what is asserted here, over the values that break naive escaping.
 */
final class DatabaseBackupTest extends IntegrationTestCase
{
    private const TABLE = 'backup_round_trip';

    protected bool $useTransaction = false;

    private string $dumpFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dumpFile = tempnam(sys_get_temp_dir(), 'thelia-backup-').'.sql';
        $this->createTable();
    }

    protected function tearDown(): void
    {
        $this->connection()->exec('DROP TABLE IF EXISTS `'.self::TABLE.'`');
        @unlink($this->dumpFile);

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{int|null, string}>
     */
    public static function trapValues(): iterable
    {
        yield 'null in a nullable integer column' => [null, 'the value that used to break the whole restore'];
        yield 'literal backslashes' => [1, 'C:\\path\\to\\file, and a lone \\ here'];
        yield 'a backslash-n that is not a newline' => [2, 'not a newline: \\n \\r \\t'];
        yield 'real newlines' => [3, "line one\nline two\r\nline three"];
        yield 'quotes of both kinds' => [4, "l'apostrophe et \"les guillemets\""];
        yield 'a semicolon before a newline' => [5, "a statement separator lives here;\nand the value goes on"];
        yield 'multibyte utf8' => [6, 'accentué — ligature œ, cyrillique Мехико, 漢字'];
        yield 'four-byte utf8mb4 emoji' => [7, 'panier 🛒 validé ✅ 👍🏽'];
        yield 'sql that would run if it escaped its quotes' => [8, "'); DROP TABLE `victim`; --"];
        yield 'control characters' => [9, "tab\there, ctrl-z \x1a, and a NUL-free tail"];
        yield 'the empty string, which is not null' => [10, ''];
        // A pipe is an ordinary character in data, and it was the placeholder the
        // statement splitter used internally: every one of them came back a semicolon.
        yield 'pipes, as a rich text toolbar or a csv field holds them' => [11, 'undo,redo,|,bold,italic,|,link'];
        // Text that looks like the markers the splitter uses internally.
        yield 'text shaped like an internal marker' => [12, "a -CODE- b -SEMICOLON- c ;', d"];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('trapValues')]
    public function testAValueComesBackAsItWentIn(?int $nullableInt, string $payload): void
    {
        $this->insert($nullableInt, $payload);
        $before = $this->rows();

        $this->database()->backupDb($this->dumpFile, [self::TABLE]);
        $this->database()->restoreDb($this->dumpFile);

        self::assertSame($before, $this->rows());
    }

    public function testEveryTrapValueSurvivesTheSameDump(): void
    {
        foreach (self::trapValues() as $case) {
            $this->insert($case[0], $case[1]);
        }
        $before = $this->rows();
        self::assertCount(iterator_count(self::trapValues()), $before);

        $this->database()->backupDb($this->dumpFile, [self::TABLE]);
        $this->database()->restoreDb($this->dumpFile);

        self::assertSame($before, $this->rows());
    }

    public function testANullIsDumpedAsNullRatherThanAnEmptyString(): void
    {
        $this->insert(null, 'anything');

        $this->database()->backupDb($this->dumpFile, [self::TABLE]);

        // Read on the file itself: an empty string here is what MariaDB rejects for an
        // integer column under STRICT_TRANS_TABLES, and it rejects it mid-restore.
        self::assertStringContainsString(',NULL,', (string) file_get_contents($this->dumpFile));
    }

    public function testATruncatedDumpIsRefusedBeforeAnythingIsDropped(): void
    {
        $this->insert(1, 'a row worth keeping');
        $before = $this->rows();

        $this->database()->backupDb($this->dumpFile, [self::TABLE]);
        $dump = (string) file_get_contents($this->dumpFile);
        file_put_contents($this->dumpFile, substr($dump, 0, (int) (\strlen($dump) / 2)));

        // self::fail() raises a RuntimeException of its own, so the outcome is recorded
        // and asserted outside the catch rather than from inside it.
        $message = null;

        try {
            $this->database()->restoreDb($this->dumpFile);
        } catch (\RuntimeException $exception) {
            $message = $exception->getMessage();
        }

        self::assertNotNull($message, 'A truncated dump must not be replayed.');
        self::assertStringContainsString('truncated', $message);

        // The point of checking first: the table is still there, with its rows.
        self::assertSame($before, $this->rows());
    }

    public function testAFailedRestoreSaysWhichTableItStoppedOn(): void
    {
        $this->insert(1, 'a row');
        $this->database()->backupDb($this->dumpFile, [self::TABLE]);

        // Narrow the column the dump recreates, so its own INSERT no longer fits it:
        // the same rejection, mid-restore, that an empty string for an integer produced.
        $dump = (string) file_get_contents($this->dumpFile);
        file_put_contents($this->dumpFile, str_replace('`payload` longtext', '`payload` int(11)', $dump));

        $message = null;

        try {
            $this->database()->restoreDb($this->dumpFile);
        } catch (\RuntimeException $exception) {
            $message = $exception->getMessage();
        }

        self::assertNotNull($message, 'A dump that cannot apply must be reported.');
        self::assertStringContainsString(self::TABLE, $message);
        self::assertStringContainsString('the ones after it still hold what they held', $message);
    }

    private function createTable(): void
    {
        $this->connection()->exec('DROP TABLE IF EXISTS `'.self::TABLE.'`');
        $this->connection()->exec(
            'CREATE TABLE `'.self::TABLE.'` (
                `id` INTEGER NOT NULL AUTO_INCREMENT,
                `nullable_int` INTEGER NULL,
                `payload` LONGTEXT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB CHARACTER SET=\'utf8mb4\' COLLATE=\'utf8mb4_general_ci\'',
        );
    }

    private function insert(?int $nullableInt, string $payload): void
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO `'.self::TABLE.'` (`nullable_int`, `payload`) VALUES (?, ?)',
        );
        $statement->execute([$nullableInt, $payload]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        return $this->connection()
            ->query('SELECT * FROM `'.self::TABLE.'` ORDER BY `id`')
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function testADelimiterBlockStillKeepsItsOwnSemicolons(): void
    {
        // The placeholder exists for this: thelia.sql ships DELIMITER blocks, whose
        // inner semicolons must not cut the statement in two.
        $file = "DELIMITER //\nCREATE TRIGGER t BEGIN SET @a = 1; SET @b = 2; END//\nDELIMITER ;\nSELECT 1;\n";

        $statements = $this->database()->statementsOf($file);
        $trigger = implode('', array_filter($statements, static fn (string $s) => str_contains($s, 'TRIGGER')));

        self::assertStringContainsString('SET @a = 1; SET @b = 2;', $trigger);
    }

    public function testAPipeIsAcceptableAsADelimiter(): void
    {
        // It used to be rejected, because '|' was the placeholder the splitter used.
        $file = "DELIMITER |\nCREATE TRIGGER t BEGIN SET @a = 1; SET @b = 2; END|\nDELIMITER ;\n";

        $statements = $this->database()->statementsOf($file);
        $trigger = implode('', array_filter($statements, static fn (string $s) => str_contains($s, 'TRIGGER')));

        self::assertStringContainsString('SET @a = 1; SET @b = 2;', $trigger);
    }

    private function database(): DatabaseExposingItsParser
    {
        return new DatabaseExposingItsParser($this->connection());
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getConnection(ProductTableMap::DATABASE_NAME);
    }
}

/**
 * prepareSql() is protected, and how it cuts a file into statements is exactly what the
 * pipe regression was about, so it is reached here rather than inferred.
 */
final class DatabaseExposingItsParser extends Database
{
    /**
     * @return list<string>
     */
    public function statementsOf(string $sql): array
    {
        return array_values($this->prepareSql($sql));
    }
}
