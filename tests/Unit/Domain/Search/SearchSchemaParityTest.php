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

namespace Thelia\Tests\Unit\Domain\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SearchSchemaParityTest extends TestCase
{
    private const string UPDATE_SCRIPT = 'setup/update/sql/3.5.0.sql';

    /**
     * @return iterable<string, array{string}>
     */
    public static function tables(): iterable
    {
        yield 'search_synonym' => ['search_synonym'];
        yield 'search_term_redirect' => ['search_term_redirect'];
    }

    #[DataProvider('tables')]
    public function testTheUpdateCreatesTheTableTheFreshInstallCreates(string $table): void
    {
        self::assertSame(
            $this->definitionsOf($this->freshInstall(), $table),
            $this->definitionsOf($this->updateScript(), $table),
        );
    }

    #[DataProvider('tables')]
    public function testTheFreshInstallCreatesTheTableTheModelDeclares(string $table): void
    {
        self::assertSame(1, preg_match('#<table name="'.$table.'".*?</table>#s', (string) file_get_contents($this->root().'/local/config/schema.xml'), $model));
        preg_match_all('#<column [^>]*name="([^"]+)"#', $model[0], $columns);
        preg_match_all('#<(?:index|unique|foreign-key) [^>]*name="([^"]+)"#', $model[0], $keys);
        $expected = [...$columns[1], 'created_at', 'updated_at', ...$keys[1]];
        sort($expected);

        $found = [];

        foreach ($this->definitionsOf($this->freshInstall(), $table) as $definition) {
            if (1 === preg_match('#^(?:`([a-z_]+)`|(?:UNIQUE )?INDEX `([^`]+)`|CONSTRAINT `([^`]+)`)#', $definition, $name)) {
                $found[] = $name[3] ?? $name[2] ?? $name[1];
            }
        }

        sort($found);

        self::assertSame($expected, array_values(array_filter($found)));
    }

    public function testTheUpdateCreatesEachTableOnlyWhenItIsMissing(): void
    {
        $script = $this->updateScript();

        foreach (array_keys(iterator_to_array(self::tables())) as $table) {
            self::assertStringContainsString("AND `TABLE_NAME` = '".$table."');", $script);
        }

        $statements = preg_match_all('#^SET @statement\b#m', $script);

        foreach (['PREPARE', 'EXECUTE', 'DEALLOCATE PREPARE'] as $statement) {
            self::assertSame($statements, preg_match_all('#^'.$statement.' #m', $script), $statement);
        }
    }

    /**
     * @return list<string>
     */
    private function definitionsOf(string $sql, string $table): array
    {
        $start = strpos($sql, 'CREATE TABLE `'.$table.'`');
        self::assertNotFalse($start, $table);
        $body = substr($sql, $start, (int) strpos($sql, 'ENGINE=', $start) - $start);
        $body = (string) preg_replace('#\s+#', ' ', $body);
        $body = trim(substr($body, (int) strpos($body, '(') + 1));
        $body = (string) preg_replace('#\s*\)\s*$#', '', $body);

        $definitions = [];
        $depth = 0;
        $current = '';

        foreach (str_split($body) as $character) {
            $depth += match ($character) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if (',' === $character && 0 === $depth) {
                $definitions[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $character;
        }

        $definitions[] = trim($current);

        return array_map(static fn (string $definition): string => (string) preg_replace(['#\( #', '# \)#'], ['(', ')'], $definition), $definitions);
    }

    private function freshInstall(): string
    {
        return (string) file_get_contents($this->root().'/setup/thelia.sql');
    }

    private function updateScript(): string
    {
        return (string) file_get_contents($this->root().'/'.self::UPDATE_SCRIPT);
    }

    private function root(): string
    {
        return \dirname(__DIR__, 4);
    }
}
