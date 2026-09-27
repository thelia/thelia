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
use Thelia\Model\Map\ProductImageTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The statements 3.2.0.sql runs to bring the image file names back from the translations,
 * where Thelia 2.6 moved them, onto the image tables Thelia 3 reads them from.
 *
 * Each case puts the six image tables in their Thelia 2.6 shape (the file on the
 * translations, none on the image), seeds images the way a 2.6 shop holds them, runs the
 * statements read from the shipped script, then reads the image tables back. A schema change
 * commits on its own, so nothing runs in a transaction: the rows are removed and the fresh
 * install schema is put back after each case, along with the file of every image the test
 * database already held.
 */
final class ImageFileFromTranslationsMigrationTest extends IntegrationTestCase
{
    /**
     * Every image table Thelia 2.6 took the file away from, with the column naming its owner.
     */
    private const array IMAGE_TABLES = [
        'product_image' => 'product_id',
        'category_image' => 'category_id',
        'content_image' => 'content_id',
        'folder_image' => 'folder_id',
        'brand_image' => 'brand_id',
        'module_image' => 'module_id',
    ];

    /**
     * Far above anything the test database holds, so the rows of a case are told apart
     * from the others and removed without touching them.
     */
    private const int FIRST_ID = 900000001;

    protected bool $useTransaction = false;

    /** @var array<string, array<int, string>> */
    private array $filesBefore = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $freshInstallColumns = [];

    private string $defaultLocale;

    private string $otherLocale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLocale = (string) $this->connection()->query('SELECT `locale` FROM `lang` WHERE `by_default` = 1 ORDER BY `id` LIMIT 1')->fetchColumn();
        // The other language is the first one in alphabetical order, the one the fallback would
        // pick: a script that ignored the default language would take its file and fail here.
        $this->otherLocale = (string) $this->connection()->query('SELECT `locale` FROM `lang` WHERE `by_default` = 0 ORDER BY `locale` LIMIT 1')->fetchColumn();
        self::assertNotSame('', $this->defaultLocale, 'The test database has no default language.');
        self::assertNotSame('', $this->otherLocale, 'The test database has a single language.');

        foreach (array_keys(self::IMAGE_TABLES) as $table) {
            $this->freshInstallColumns[$table] = $this->columnsOf($table);
            $this->freshInstallColumns[$table.'_i18n'] = $this->columnsOf($table.'_i18n');
            $this->filesBefore[$table] = $this->connection()->query(\sprintf('SELECT `id`, `file` FROM `%s`', $table))->fetchAll(\PDO::FETCH_KEY_PAIR);
        }
    }

    protected function tearDown(): void
    {
        $connection = $this->connection();

        foreach (self::IMAGE_TABLES as $table => $owner) {
            $connection->exec(\sprintf('DELETE FROM `%s` WHERE `id` >= %d', $table, self::FIRST_ID));

            if ($this->hasColumn($table.'_i18n', 'file')) {
                $connection->exec(\sprintf('ALTER TABLE `%s_i18n` DROP COLUMN `file`', $table));
            }

            if (!$this->hasColumn($table, 'file')) {
                $connection->exec(\sprintf('ALTER TABLE `%s` ADD `file` VARCHAR(255) NOT NULL AFTER `%s`', $table, $owner));
            }

            $restore = $connection->prepare(\sprintf('UPDATE `%s` SET `file` = :file WHERE `id` = :id', $table));

            foreach ($this->filesBefore[$table] ?? [] as $id => $file) {
                $restore->execute(['file' => $file, 'id' => $id]);
            }
        }

        parent::tearDown();
    }

    public function testEachImageGetsTheFileOfTheDefaultLanguage(): void
    {
        $this->putTheImageTablesInTheirThelia26Shape();

        foreach (array_keys(self::IMAGE_TABLES) as $table) {
            $this->seedImage($table, self::FIRST_ID, [
                $this->defaultLocale => $table.'-default.jpg',
                $this->otherLocale => $table.'-other.jpg',
            ]);
        }

        $this->runMigration();

        foreach (array_keys(self::IMAGE_TABLES) as $table) {
            self::assertSame($table.'-default.jpg', $this->fileOf($table, self::FIRST_ID), $table);
        }
    }

    public function testAnImageWithoutFileInTheDefaultLanguageGetsTheFileOfAnotherLanguage(): void
    {
        $this->putTheImageTablesInTheirThelia26Shape();

        $this->seedImage('product_image', self::FIRST_ID, [
            $this->defaultLocale => '',
            $this->otherLocale => 'empty-in-default.jpg',
        ]);
        $this->seedImage('product_image', self::FIRST_ID + 1, [
            $this->otherLocale => 'untranslated-in-default.jpg',
        ]);
        $this->seedImage('product_image', self::FIRST_ID + 2, [
            $this->defaultLocale => 'only-in-default.jpg',
            $this->otherLocale => '',
        ]);

        $this->runMigration();

        self::assertSame('empty-in-default.jpg', $this->fileOf('product_image', self::FIRST_ID));
        self::assertSame('untranslated-in-default.jpg', $this->fileOf('product_image', self::FIRST_ID + 1));
        self::assertSame('only-in-default.jpg', $this->fileOf('product_image', self::FIRST_ID + 2));
    }

    public function testTheSchemaEndsUpAsTheFreshInstallOne(): void
    {
        $this->putTheImageTablesInTheirThelia26Shape();

        $this->runMigration();

        foreach ($this->freshInstallColumns as $table => $columns) {
            self::assertSame($columns, $this->columnsOf($table), $table);
        }
    }

    public function testTheStatementsCanBeReplayed(): void
    {
        $this->putTheImageTablesInTheirThelia26Shape();

        $this->seedImage('product_image', self::FIRST_ID, [
            $this->defaultLocale => 'replayed.jpg',
            $this->otherLocale => 'replayed-other.jpg',
        ]);

        $this->runMigration();
        $this->runMigration();

        self::assertSame('replayed.jpg', $this->fileOf('product_image', self::FIRST_ID));

        foreach ($this->freshInstallColumns as $table => $columns) {
            self::assertSame($columns, $this->columnsOf($table), $table);
        }
    }

    public function testAShopThatNeverRanThelia26IsLeftAlone(): void
    {
        foreach (self::IMAGE_TABLES as $table => $owner) {
            $this->insertWithoutOwner(\sprintf('INSERT INTO `%s` (`id`, `%s`, `file`) VALUES (%d, %d, \'kept.jpg\')', $table, $owner, self::FIRST_ID, self::FIRST_ID));
            $this->insertWithoutOwner(\sprintf('INSERT INTO `%s_i18n` (`id`, `locale`, `title`) VALUES (%d, \'%s\', \'Title\')', $table, self::FIRST_ID, $this->defaultLocale));
        }

        $this->runMigration();

        foreach (array_keys(self::IMAGE_TABLES) as $table) {
            self::assertSame('kept.jpg', $this->fileOf($table, self::FIRST_ID), $table);
        }

        foreach ($this->freshInstallColumns as $table => $columns) {
            self::assertSame($columns, $this->columnsOf($table), $table);
        }
    }

    /**
     * What Thelia 2.6 left: the file on every translation of the image, none on the image.
     */
    private function putTheImageTablesInTheirThelia26Shape(): void
    {
        foreach (array_keys(self::IMAGE_TABLES) as $table) {
            $this->connection()->exec(\sprintf('ALTER TABLE `%s_i18n` ADD `file` VARCHAR(255) NOT NULL AFTER `locale`', $table));
            $this->connection()->exec(\sprintf('UPDATE `%1$s_i18n` `translation` INNER JOIN `%1$s` `image` ON `image`.`id` = `translation`.`id` SET `translation`.`file` = `image`.`file`', $table));
            $this->connection()->exec(\sprintf('ALTER TABLE `%s` DROP COLUMN `file`', $table));
        }
    }

    /**
     * @param array<string, string> $files the file of the image in each language it is translated in
     */
    private function seedImage(string $table, int $id, array $files): void
    {
        $this->insertWithoutOwner(\sprintf('INSERT INTO `%s` (`id`, `%s`) VALUES (%d, %d)', $table, self::IMAGE_TABLES[$table], $id, $id));

        $translation = $this->connection()->prepare(\sprintf('INSERT INTO `%s_i18n` (`id`, `locale`, `file`) VALUES (:id, :locale, :file)', $table));

        foreach ($files as $locale => $file) {
            $translation->execute(['id' => $id, 'locale' => $locale, 'file' => $file]);
        }
    }

    /**
     * The images of a case belong to no catalogue row: what the statements read is the image
     * and its translations, and the rows are removed by id afterwards.
     */
    private function insertWithoutOwner(string $sql): void
    {
        $connection = $this->connection();
        $connection->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $connection->exec($sql);
        } finally {
            $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function runMigration(): void
    {
        foreach ($this->migrationStatements() as $statement) {
            $this->connection()->exec($statement);
        }
    }

    /**
     * @return list<string>
     */
    private function migrationStatements(): array
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.2.0.sql');

        $statements = [];

        foreach (explode(";\n", $script) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (str_contains($sql, 'image_file_')) {
                $statements[] = $sql;
            }
        }

        self::assertCount(14 * \count(self::IMAGE_TABLES), $statements, 'The 3.2.0 script does not bring the image files back from the translations.');

        return $statements;
    }

    private function fileOf(string $table, int $id): ?string
    {
        $statement = $this->connection()->prepare(\sprintf('SELECT `file` FROM `%s` WHERE `id` = :id', $table));
        $statement->execute(['id' => $id]);
        $file = $statement->fetchColumn();

        return false === $file ? null : (string) $file;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $statement = $this->connection()->prepare('SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table AND `COLUMN_NAME` = :column');
        $statement->execute(['table' => $table, 'column' => $column]);

        return 0 < (int) $statement->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columnsOf(string $table): array
    {
        $statement = $this->connection()->prepare('SELECT `COLUMN_NAME`, `ORDINAL_POSITION`, `COLUMN_TYPE`, `IS_NULLABLE`, `COLUMN_DEFAULT` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table ORDER BY `ORDINAL_POSITION`');
        $statement->execute(['table' => $table]);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getWriteConnection(ProductImageTableMap::DATABASE_NAME);
    }
}
