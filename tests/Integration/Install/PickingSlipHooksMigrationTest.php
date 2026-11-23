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
use Thelia\Model\Map\HookTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The PDF hooks of the picking slip, declared by the 3.5.0 update script: a shop updated from
 * 3.4 has none of them, since the 3.4.0 script is never replayed on it. The block runs on a
 * database that lost them, inside the transaction of the test, and can run again.
 */
final class PickingSlipHooksMigrationTest extends IntegrationTestCase
{
    private const SCRIPT = '3.5.0.sql';
    private const BLOCK_TITLE = '-- PDF hooks of the picking slip template';
    private const HOOKS = ['preparation.css', 'preparation.after-information', 'preparation.product-location'];

    public function testAShopWithoutTheHooksGetsThemWithTheirTitlesAndTheBlockCanBeReplayed(): void
    {
        $this->connection()->exec(\sprintf("DELETE FROM `hook` WHERE `type` = 3 AND `code` IN ('%s')", implode("', '", self::HOOKS)));
        self::assertSame([], $this->hooks());

        $this->runBlock();
        $this->runBlock();

        $hooks = $this->hooks();
        self::assertSame(self::HOOKS, array_keys($hooks), 'each hook once, even when the block runs twice');

        foreach ($hooks as $code => $titles) {
            self::assertNotEmpty($titles['en_US'] ?? null, $code.' has an English title');
            self::assertNotEmpty($titles['fr_FR'] ?? null, $code.' has a French title');
        }

        self::assertSame('Picking slip - storage location of a line', $hooks['preparation.product-location']['en_US']);
    }

    public function testTheBlockIsNotInTheReleasedScript(): void
    {
        $released = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.4.0.sql');

        self::assertStringNotContainsString(self::BLOCK_TITLE, $released, 'a shop already in 3.4.0 never replays that script');
    }

    /**
     * @return array<string, array<string, string|null>> titles by locale, by hook code, in the order of HOOKS
     */
    private function hooks(): array
    {
        $rows = $this->connection()->query(\sprintf(
            "SELECT `hook`.`code`, `hook_i18n`.`locale`, `hook_i18n`.`title` FROM `hook` LEFT JOIN `hook_i18n` ON `hook_i18n`.`id` = `hook`.`id` WHERE `hook`.`type` = 3 AND `hook`.`code` IN ('%s')",
            implode("', '", self::HOOKS),
        ))->fetchAll(\PDO::FETCH_ASSOC);

        $hooks = [];
        $counts = [];

        foreach ($rows as $row) {
            $hooks[$row['code']] ??= [];
            if (null !== $row['locale']) {
                $hooks[$row['code']][$row['locale']] = $row['title'];
            }
        }

        foreach ($this->connection()->query(\sprintf(
            "SELECT `code`, COUNT(*) AS `n` FROM `hook` WHERE `type` = 3 AND `code` IN ('%s') GROUP BY `code`",
            implode("', '", self::HOOKS),
        ))->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $counts[$row['code']] = (int) $row['n'];
        }

        foreach ($counts as $code => $count) {
            self::assertSame(1, $count, $code.' is declared once');
        }

        return array_filter(array_replace(array_fill_keys(self::HOOKS, null), $hooks), static fn (?array $titles): bool => null !== $titles);
    }

    private function runBlock(): void
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
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.self::SCRIPT);
        $start = strpos($script, self::BLOCK_TITLE);
        self::assertIsInt($start, 'The update script has no such block: '.self::BLOCK_TITLE);

        // The block runs from the end of its header to the header of the next block, or to the end of the script.
        $headerEnd = strpos($script, "\n-- ----", $start);
        self::assertIsInt($headerEnd);
        $end = strpos($script, "\n-- ----", $headerEnd + 1);
        $end = false !== $end ? $end : strpos($script, 'SET FOREIGN_KEY_CHECKS = 1', $headerEnd);
        self::assertIsInt($end);

        $statements = [];

        foreach (explode(";\n", substr($script, $headerEnd + 1, $end - $headerEnd - 1)) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if ('' !== $sql) {
                $statements[] = $sql;
            }
        }

        self::assertNotEmpty($statements);

        return $statements;
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getWriteConnection(HookTableMap::DATABASE_NAME);
    }
}
