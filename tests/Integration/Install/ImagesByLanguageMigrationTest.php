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
 * The block of 3.2.0.sql that moves the file of the six image tables into their
 * translations, where each language keeps its own.
 *
 * Each case puts the image tables in the shape a shop holds them in before the update
 * (the file on the image for a 3.0/3.1 shop or one that came from 2.5, on the
 * translations and NOT NULL for one that came from 2.6), seeds images, runs the
 * statements read from the shipped script, then reads the translations back. A schema
 * change commits on its own, so nothing runs in a transaction: the rows of a case are
 * removed, and the fresh install schema and the files the test database held are put
 * back after each case.
 */
final class ImagesByLanguageMigrationTest extends IntegrationTestCase
{
    /**
     * Every image table whose file is translated, with the column naming its owner.
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

    /** @var array<string, list<array{id: int, locale: string, file: string|null}>> */
    private array $filesBefore = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $freshInstallColumns = [];

    private string $defaultLocale;

    private string $otherLocale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLocale = (string) $this->connection()->query('SELECT `locale` FROM `lang` WHERE `by_default` = 1 ORDER BY `id` LIMIT 1')->fetchColumn();
        $this->otherLocale = (string) $this->connection()->query('SELECT `locale` FROM `lang` WHERE `by_default` = 0 ORDER BY `locale` LIMIT 1')->fetchColumn();
        self::assertNotSame('', $this->defaultLocale, 'The test database has no default language.');
        self::assertNotSame('', $this->otherLocale, 'The test database has a single language.');

        foreach (array_keys(self::IMAGE_TABLES) as $table) {
            $this->freshInstallColumns[$table] = $this->columnsOf($table);
            $this->freshInstallColumns[$table.'_i18n'] = $this->columnsOf($table.'_i18n');
            $this->filesBefore[$table] = $this->connection()->query(\sprintf('SELECT `id`, `locale`, `file` FROM `%s_i18n`', $table))->fetchAll(\PDO::FETCH_ASSOC);
        }
    }

    protected function tearDown(): void
    {
        $connection = $this->connection();

        foreach (array_keys(self::IMAGE_TABLES) as $table) {
            $connection->exec(\sprintf('DELETE FROM `%s_i18n` WHERE `id` >= %d', $table, self::FIRST_ID));
            $connection->exec(\sprintf('DELETE FROM `%s` WHERE `id` >= %d', $table, self::FIRST_ID));

            if ($this->hasColumn($table, 'file')) {
                $connection->exec(\sprintf('ALTER TABLE `%s` DROP COLUMN `file`', $table));
            }

            if ($this->hasColumn($table.'_i18n', 'file')) {
                $connection->exec(\sprintf('ALTER TABLE `%s_i18n` MODIFY `file` VARCHAR(255) NULL', $table));
            } else {
                $connection->exec(\sprintf('ALTER TABLE `%s_i18n` ADD `file` VARCHAR(255) NULL AFTER `locale`', $table));
            }

            $restore = $connection->prepare(\sprintf('UPDATE `%s_i18n` SET `file` = :file WHERE `id` = :id AND `locale` = :locale', $table));

            foreach ($this->filesBefore[$table] ?? [] as $row) {
                $restore->execute($row);
            }
        }

        parent::tearDown();
    }

    /**
     * Cases (a) and (c): a 3.0/3.1 shop, or one that came from 2.5 or earlier.
     */
    public function testTheFileOfTheImageIsCopiedIntoEveryTranslationItHas(): void
    {
        $this->putTheImageTablesInTheirThelia3Shape();

        foreach (array_keys(self::IMAGE_TABLES) as $table) {
            $this->seedUntranslatedImage($table, self::FIRST_ID, $table.'.jpg', [$this->defaultLocale, $this->otherLocale]);
        }

        $this->runMigration();

        foreach (array_keys(self::IMAGE_TABLES) as $table) {
            self::assertSame(
                [$this->defaultLocale => $table.'.jpg', $this->otherLocale => $table.'.jpg'],
                $this->filesOf($table, self::FIRST_ID),
                $table,
            );
        }
    }

    public function testAnImageWithoutTranslationGetsOneInTheDefaultLanguageOnly(): void
    {
        $this->putTheImageTablesInTheirThelia3Shape();

        $this->seedUntranslatedImage('product_image', self::FIRST_ID, 'no-translation.jpg', []);
        $this->seedUntranslatedImage('product_image', self::FIRST_ID + 1, 'other-language-only.jpg', [$this->otherLocale]);

        $this->runMigration();

        self::assertSame([$this->defaultLocale => 'no-translation.jpg'], $this->filesOf('product_image', self::FIRST_ID));
        self::assertSame(
            [$this->defaultLocale => 'other-language-only.jpg', $this->otherLocale => 'other-language-only.jpg'],
            $this->filesOf('product_image', self::FIRST_ID + 1),
        );
    }

    /**
     * Case (b): the files are already in the translations, one per language.
     */
    public function testAShopThatCameFromThelia26KeepsTheFileOfEachLanguage(): void
    {
        $this->putTheImageTablesInTheirThelia26Shape();

        foreach (array_keys(self::IMAGE_TABLES) as $table) {
            $this->seedTranslatedImage($table, self::FIRST_ID, [
                $this->defaultLocale => $table.'-default.jpg',
                $this->otherLocale => $table.'-other.jpg',
            ]);
        }

        $this->seedTranslatedImage('product_image', self::FIRST_ID + 1, [
            $this->defaultLocale => 'only-in-default.jpg',
            $this->otherLocale => '',
        ]);

        $this->runMigration();

        foreach (array_keys(self::IMAGE_TABLES) as $table) {
            self::assertSame(
                [$this->defaultLocale => $table.'-default.jpg', $this->otherLocale => $table.'-other.jpg'],
                $this->filesOf($table, self::FIRST_ID),
                $table,
            );
        }

        self::assertSame(
            [$this->defaultLocale => 'only-in-default.jpg', $this->otherLocale => null],
            $this->filesOf('product_image', self::FIRST_ID + 1),
            'An empty file name is stored as "no file", which falls back on the default language.',
        );
    }

    /**
     * A 2.6 shop whose image table got its column back: the translations win, the
     * image file only fills the ones that have none.
     */
    public function testAFileLeftOnTheImageOverwritesNoTranslatedFile(): void
    {
        $this->putTheImageTablesInTheirThelia26Shape();

        foreach (self::IMAGE_TABLES as $table => $owner) {
            $this->connection()->exec(\sprintf('ALTER TABLE `%s` ADD `file` VARCHAR(255) NOT NULL DEFAULT \'\' AFTER `%s`', $table, $owner));
        }

        $this->seedTranslatedImage('product_image', self::FIRST_ID, [
            $this->defaultLocale => 'translated-default.jpg',
            $this->otherLocale => '',
        ]);
        $this->connection()->exec(\sprintf('UPDATE `product_image` SET `file` = \'left-on-the-image.jpg\' WHERE `id` = %d', self::FIRST_ID));

        $this->runMigration();

        self::assertSame(
            [$this->defaultLocale => 'translated-default.jpg', $this->otherLocale => 'left-on-the-image.jpg'],
            $this->filesOf('product_image', self::FIRST_ID),
        );
    }

    public function testTheSchemaEndsUpAsTheFreshInstallOneFromEitherShape(): void
    {
        $this->putTheImageTablesInTheirThelia3Shape();
        $this->runMigration();
        $this->assertFreshInstallSchema();

        $this->putTheImageTablesInTheirThelia26Shape();
        $this->runMigration();
        $this->assertFreshInstallSchema();
    }

    public function testTheStatementsCanBeReplayed(): void
    {
        $this->putTheImageTablesInTheirThelia3Shape();
        $this->seedUntranslatedImage('product_image', self::FIRST_ID, 'replayed.jpg', [$this->otherLocale]);

        $this->runMigration();
        $this->runMigration();

        self::assertSame(
            [$this->defaultLocale => 'replayed.jpg', $this->otherLocale => 'replayed.jpg'],
            $this->filesOf('product_image', self::FIRST_ID),
        );
        $this->assertFreshInstallSchema();
    }

    public function testADatabaseAlreadyUpToDateIsLeftAlone(): void
    {
        foreach (self::IMAGE_TABLES as $table => $owner) {
            $this->insertWithoutOwner(\sprintf('INSERT INTO `%s` (`id`, `%s`) VALUES (%d, %d)', $table, $owner, self::FIRST_ID, self::FIRST_ID));
            $this->insertWithoutOwner(\sprintf(
                'INSERT INTO `%s_i18n` (`id`, `locale`, `file`) VALUES (%d, \'%s\', \'kept-default.jpg\'), (%d, \'%s\', NULL)',
                $table,
                self::FIRST_ID,
                $this->defaultLocale,
                self::FIRST_ID,
                $this->otherLocale,
            ));
        }

        $this->runMigration();

        foreach (array_keys(self::IMAGE_TABLES) as $table) {
            self::assertSame(
                [$this->defaultLocale => 'kept-default.jpg', $this->otherLocale => null],
                $this->filesOf($table, self::FIRST_ID),
                $table,
            );
        }

        $this->assertFreshInstallSchema();
    }

    /**
     * What Thelia 3.0 and 3.1 hold, like 2.5: the file on the image, none on the translations.
     */
    private function putTheImageTablesInTheirThelia3Shape(): void
    {
        foreach (self::IMAGE_TABLES as $table => $owner) {
            if ($this->hasColumn($table.'_i18n', 'file')) {
                $this->connection()->exec(\sprintf('ALTER TABLE `%s_i18n` DROP COLUMN `file`', $table));
            }

            if (!$this->hasColumn($table, 'file')) {
                $this->connection()->exec(\sprintf('ALTER TABLE `%s` ADD `file` VARCHAR(255) NOT NULL AFTER `%s`', $table, $owner));
            }
        }
    }

    /**
     * What Thelia 2.6 left: the file on every translation, NOT NULL, none on the image.
     */
    private function putTheImageTablesInTheirThelia26Shape(): void
    {
        foreach (array_keys(self::IMAGE_TABLES) as $table) {
            if ($this->hasColumn($table, 'file')) {
                $this->connection()->exec(\sprintf('ALTER TABLE `%s` DROP COLUMN `file`', $table));
            }

            if ($this->hasColumn($table.'_i18n', 'file')) {
                $this->connection()->exec(\sprintf('UPDATE `%s_i18n` SET `file` = \'\' WHERE `file` IS NULL', $table));
                $this->connection()->exec(\sprintf('ALTER TABLE `%s_i18n` MODIFY `file` VARCHAR(255) NOT NULL', $table));
            } else {
                $this->connection()->exec(\sprintf('ALTER TABLE `%s_i18n` ADD `file` VARCHAR(255) NOT NULL AFTER `locale`', $table));
            }
        }
    }

    /**
     * @param list<string> $locales the languages the image is translated in
     */
    private function seedUntranslatedImage(string $table, int $id, string $file, array $locales): void
    {
        $this->insertWithoutOwner(\sprintf('INSERT INTO `%s` (`id`, `%s`, `file`) VALUES (%d, %d, \'%s\')', $table, self::IMAGE_TABLES[$table], $id, $id, $file));

        foreach ($locales as $locale) {
            $this->insertWithoutOwner(\sprintf('INSERT INTO `%s_i18n` (`id`, `locale`, `title`) VALUES (%d, \'%s\', \'Title\')', $table, $id, $locale));
        }
    }

    /**
     * @param array<string, string> $files the file of the image in each language it is translated in
     */
    private function seedTranslatedImage(string $table, int $id, array $files): void
    {
        $this->insertWithoutOwner(\sprintf('INSERT INTO `%s` (`id`, `%s`) VALUES (%d, %d)', $table, self::IMAGE_TABLES[$table], $id, $id));

        foreach ($files as $locale => $file) {
            $this->insertWithoutOwner(\sprintf('INSERT INTO `%s_i18n` (`id`, `locale`, `file`) VALUES (%d, \'%s\', \'%s\')', $table, $id, $locale, $file));
        }
    }

    /**
     * The images of a case belong to no catalogue row: what the statements read is the
     * image and its translations, and the rows are removed by id afterwards.
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
     * The statements of the block, cut the way the updater cuts a script.
     *
     * @return list<string>
     */
    private function migrationStatements(): array
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.2.0.sql');

        $statements = [];

        foreach (explode(";\n", $script) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (str_contains($sql, 'image_file_') || 1 === preg_match('/^UPDATE `\w+_image_i18n` SET `file` = NULL/', $sql)) {
                $statements[] = $sql;
            }
        }

        $drops = array_filter($statements, static fn (string $sql): bool => str_contains($sql, 'DROP COLUMN `file`'));
        self::assertCount(\count(self::IMAGE_TABLES), $drops, 'The 3.2.0 script does not move the image files into their translations.');

        return $statements;
    }

    /**
     * @return array<string, string|null> the file of each translation of the image, by locale
     */
    private function filesOf(string $table, int $id): array
    {
        $statement = $this->connection()->prepare(\sprintf('SELECT `locale`, `file` FROM `%s_i18n` WHERE `id` = :id ORDER BY `locale` = :default DESC, `locale`', $table));
        $statement->execute(['id' => $id, 'default' => $this->defaultLocale]);

        return $statement->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    private function assertFreshInstallSchema(): void
    {
        foreach ($this->freshInstallColumns as $table => $columns) {
            self::assertSame($columns, $this->columnsOf($table), $table);
        }
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
