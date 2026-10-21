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
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\Map\FolderTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The block of the pending update script that gives a 3.2 shop the publication dates of
 * its contents and folders: two columns on each table and its versions, an index on each
 * date, and a state for every row the shop already holds.
 *
 * Each case takes the database back to its 3.2 shape, runs the block read from the shipped
 * script, and compares what it built with the fresh install. A schema change commits on its
 * own, so no transaction wraps the case; the rows written are removed by hand.
 */
final class ContentPublicationMigrationTest extends IntegrationTestCase
{
    /** The pending script, the one UpdateScriptTest names: it moves on at each release. */
    private const string PENDING_SCRIPT = '3.3.0.sql';

    private const string BLOCK_TITLE = '-- Publication of contents and folders';

    private const array TABLES = ['content', 'content_version', 'folder', 'folder_version'];

    /** Column => definition of the fresh install, in setup/thelia.sql, by table. */
    private const array COLUMNS = [
        'content' => [
            'publish_at' => "DATETIME COMMENT 'when the content went or goes online; NULL on a draft'",
            'unpublish_at' => "DATETIME COMMENT 'when the content went or goes offline; NULL while nothing takes it offline'",
        ],
        'folder' => [
            'publish_at' => "DATETIME COMMENT 'when the folder went or goes online; NULL on a draft'",
            'unpublish_at' => "DATETIME COMMENT 'when the folder went or goes offline; NULL while nothing takes it offline'",
        ],
    ];

    protected bool $useTransaction = false;

    /** @var list<int> */
    private array $contentIds = [];

    /** @var list<int> */
    private array $folderIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::TABLES as $table) {
            foreach (['content', 'folder'] as $indexed) {
                if ($table === $indexed) {
                    foreach (['publish_at', 'unpublish_at'] as $column) {
                        if ($this->hasIndex($table, \sprintf('idx_%s_%s', $table, $column))) {
                            $this->connection()->exec(\sprintf('ALTER TABLE `%s` DROP INDEX `idx_%s_%s`', $table, $table, $column));
                        }
                    }
                }
            }

            foreach (['unpublish_at', 'publish_at'] as $column) {
                if ($this->hasColumn($table, $column)) {
                    $this->connection()->exec(\sprintf('ALTER TABLE `%s` DROP COLUMN `%s`', $table, $column));
                }
            }
        }
    }

    protected function tearDown(): void
    {
        if ([] !== $this->contentIds) {
            $this->connection()->exec(\sprintf('DELETE FROM `content` WHERE `id` IN (%s)', implode(',', $this->contentIds)));
        }

        if ([] !== $this->folderIds) {
            $this->connection()->exec(\sprintf('DELETE FROM `folder` WHERE `id` IN (%s)', implode(',', $this->folderIds)));
        }

        // Put back what the fresh install has, whatever the block did or failed to do.
        foreach (self::TABLES as $table) {
            $definitions = self::COLUMNS[str_replace('_version', '', $table)];
            $after = 'position';

            foreach ($definitions as $column => $definition) {
                if (!$this->hasColumn($table, $column)) {
                    $this->connection()->exec(\sprintf('ALTER TABLE `%s` ADD `%s` %s AFTER `%s`', $table, $column, $definition, $after));
                }

                $after = $column;
            }
        }

        foreach (['content', 'folder'] as $table) {
            foreach (['publish_at', 'unpublish_at'] as $column) {
                if (!$this->hasIndex($table, \sprintf('idx_%s_%s', $table, $column))) {
                    $this->connection()->exec(\sprintf('ALTER TABLE `%s` ADD INDEX `idx_%s_%s` (`%s`)', $table, $table, $column, $column));
                }
            }
        }

        ContentTableMap::clearInstancePool();
        FolderTableMap::clearInstancePool();

        parent::tearDown();
    }

    public function testTheBlockGivesA32ShopTheColumnsAndIndexesOfTheFreshInstall(): void
    {
        $this->runTheBlock();

        foreach (self::TABLES as $table) {
            $columns = $this->columnsOf($table);

            self::assertSame(['datetime', 'YES', ''], $this->shapeOf($columns, 'publish_at'), $table);
            self::assertSame(['datetime', 'YES', ''], $this->shapeOf($columns, 'unpublish_at'), $table);

            // In the order of the fresh install, after the position.
            $names = array_keys($columns);
            $after = array_search('position', $names, true);
            self::assertIsInt($after, $table);
            self::assertSame(['publish_at', 'unpublish_at'], \array_slice($names, $after + 1, 2), $table);
        }

        foreach (['content', 'folder'] as $table) {
            self::assertTrue($this->hasIndex($table, 'idx_'.$table.'_publish_at'), $table);
            self::assertTrue($this->hasIndex($table, 'idx_'.$table.'_unpublish_at'), $table);
        }
    }

    public function testTheRowsTheShopHoldsGetAStateAndNoneBecomesADraft(): void
    {
        $visibleContent = $this->row('content', 1, '2026-01-10 10:00:00', '2026-02-10 10:00:00');
        $hiddenContent = $this->row('content', 0, '2026-01-10 10:00:00', '2026-02-10 10:00:00');
        $hiddenContentNeverUpdated = $this->row('content', 0, '2026-01-10 10:00:00', null);
        $visibleFolder = $this->row('folder', 1, '2026-03-01 08:00:00', '2026-03-02 08:00:00');
        $hiddenFolder = $this->row('folder', 0, '2026-03-01 08:00:00', '2026-03-02 08:00:00');

        $this->runTheBlock();

        self::assertSame(['2026-01-10 10:00:00', null], $this->datesOf('content', $visibleContent), 'published since its creation');
        self::assertSame([null, '2026-02-10 10:00:00'], $this->datesOf('content', $hiddenContent), 'unpublished since its last change');
        self::assertSame([null, '2026-01-10 10:00:00'], $this->datesOf('content', $hiddenContentNeverUpdated), 'its creation stands for its last change');
        self::assertSame(['2026-03-01 08:00:00', null], $this->datesOf('folder', $visibleFolder));
        self::assertSame([null, '2026-03-02 08:00:00'], $this->datesOf('folder', $hiddenFolder));

        $drafts = $this->connection()
            ->query('SELECT COUNT(*) FROM `content` WHERE (`visible` IS NULL OR `visible` <> 1) AND `publish_at` IS NULL AND `unpublish_at` IS NULL')
            ->fetchColumn();
        self::assertSame(0, (int) $drafts, 'no row of an updated shop is a draft');
    }

    public function testAReplayLeavesADraftWrittenSinceAlone(): void
    {
        $this->runTheBlock();

        // A draft and a schedule the back office wrote after the update.
        $draft = $this->row('content', 0, '2026-05-01 10:00:00', '2026-05-01 10:00:00');
        $scheduled = $this->row('content', 0, '2026-05-01 10:00:00', '2026-05-01 10:00:00');
        $this->connection()->exec(\sprintf("UPDATE `content` SET `publish_at` = '2026-12-01 09:00:00' WHERE `id` = %d", $scheduled));

        $this->runTheBlock();

        self::assertSame([null, null], $this->datesOf('content', $draft), 'a replay does not turn a draft into an unpublished row');
        self::assertSame(['2026-12-01 09:00:00', null], $this->datesOf('content', $scheduled));
        self::assertCount(2, array_intersect(array_keys($this->columnsOf('content')), ['publish_at', 'unpublish_at']));
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
        self::assertIsInt($start, 'The pending update script gives no publication dates to the contents.');

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
     * A row in the shape of a 3.2 shop: a flag and the two timestamps, nothing else.
     */
    private function row(string $table, int $visible, string $createdAt, ?string $updatedAt): int
    {
        $parent = 'folder' === $table ? '`parent`, ' : '';
        $parentValue = 'folder' === $table ? '0, ' : '';

        $this->connection()->exec(\sprintf(
            "INSERT INTO `%s` (%s`visible`, `position`, `created_at`, `updated_at`) VALUES (%s%d, 1, '%s', %s)",
            $table,
            $parent,
            $parentValue,
            $visible,
            $createdAt,
            null === $updatedAt ? 'NULL' : "'".$updatedAt."'",
        ));

        $id = (int) $this->connection()->lastInsertId();

        if ('content' === $table) {
            $this->contentIds[] = $id;
        } else {
            $this->folderIds[] = $id;
        }

        return $id;
    }

    /**
     * @return array{?string, ?string} publish_at, unpublish_at
     */
    private function datesOf(string $table, int $id): array
    {
        $row = $this->connection()->query(\sprintf('SELECT `publish_at`, `unpublish_at` FROM `%s` WHERE `id` = %d', $table, $id))->fetch(\PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return [$row['publish_at'], $row['unpublish_at']];
    }

    /**
     * @return array<string, array{COLUMN_TYPE: string, IS_NULLABLE: string, COLUMN_DEFAULT: ?string}> in their order
     */
    private function columnsOf(string $table): array
    {
        $statement = $this->connection()->prepare('SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `IS_NULLABLE`, `COLUMN_DEFAULT` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table ORDER BY `ORDINAL_POSITION`');
        $statement->execute(['table' => $table]);

        $columns = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $columns[(string) $row['COLUMN_NAME']] = $row;
        }

        return $columns;
    }

    /**
     * @param array<string, array{COLUMN_TYPE: string, IS_NULLABLE: string, COLUMN_DEFAULT: ?string}> $columns
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    private function shapeOf(array $columns, string $column): ?array
    {
        if (!isset($columns[$column])) {
            return null;
        }

        // MySQL 8 drops the display width of an integer type that MariaDB still shows, and
        // MariaDB quotes nothing for a default while MySQL may: compare the shape only.
        $type = preg_replace('/^((?:tiny|small|medium|big)?int)\(\d+\)/', '$1', (string) $columns[$column]['COLUMN_TYPE']) ?? '';
        $default = trim((string) $columns[$column]['COLUMN_DEFAULT'], "'");

        return [$type, $columns[$column]['IS_NULLABLE'], 'NULL' === $default ? '' : $default];
    }

    private function hasColumn(string $table, string $column): bool
    {
        return \array_key_exists($column, $this->columnsOf($table));
    }

    private function hasIndex(string $table, string $index): bool
    {
        $statement = $this->connection()->prepare('SELECT COUNT(*) FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table AND `INDEX_NAME` = :index');
        $statement->execute(['table' => $table, 'index' => $index]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getWriteConnection(ContentTableMap::DATABASE_NAME);
    }
}
