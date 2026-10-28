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

namespace Thelia\Tests\Unit\Domain\Ai;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The tables of the AI layer are declared in three places a fresh install and an update read separately:
 * nothing regenerates `setup/thelia.sql` for the core. Columns and indexes have to match in all three.
 */
final class AiSchemaParityTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function tables(): iterable
    {
        foreach (['ai_connection', 'ai_model', 'ai_tier_assignment', 'ai_usage_setting', 'ai_usage_log', 'ai_budget_reservation'] as $table) {
            yield $table => [$table];
        }
    }

    #[DataProvider('tables')]
    public function testTheModelTheFreshInstallAndTheUpdateDeclareTheSameTable(string $table): void
    {
        $root = \dirname(__DIR__, 4);

        $schema = (string) file_get_contents($root.'/local/config/schema.xml');
        self::assertSame(1, preg_match('#<table name="'.$table.'".*?</table>#s', $schema, $definition), 'schema.xml');
        preg_match_all('#<column [^>]*name="([^"]+)"#', $definition[0], $columns);
        preg_match_all('#<(?:index|unique) name="([^"]+)"#', $definition[0], $indexes);
        // The timestampable behaviour adds these two columns.
        $expected = ['columns' => [...$columns[1], 'created_at', 'updated_at'], 'indexes' => $indexes[1]];
        sort($expected['columns']);
        sort($expected['indexes']);

        foreach (['setup/thelia.sql' => 'CREATE TABLE `'.$table.'`', 'setup/update/sql/3.3.0.sql' => 'CREATE TABLE IF NOT EXISTS `'.$table.'`'] as $file => $start) {
            $sql = (string) file_get_contents($root.'/'.$file);
            $from = strpos($sql, $start);
            self::assertNotFalse($from, $file);
            $body = substr($sql, $from, (int) strpos($sql, 'ENGINE=', $from) - $from);
            preg_match_all('#^\s+`([a-z_]+)` [A-Z]#m', $body, $sqlColumns);
            preg_match_all('#(?:UNIQUE )?INDEX `([^`]+)`#', $body, $sqlIndexes);
            $found = ['columns' => $sqlColumns[1], 'indexes' => $sqlIndexes[1]];
            sort($found['columns']);
            sort($found['indexes']);

            self::assertSame($expected, $found, $file);
        }
    }
}
