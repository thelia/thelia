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

namespace Thelia\Tests\Unit\Install;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What an update script has to hold to be safe to run on a shop that is not a
 * fresh install: portable DDL, statements that survive a replay, and reference
 * data in every language the install seeds.
 *
 * Each of the three was broken at once on the pending script, and none of them
 * shows on a MariaDB developer machine: MariaDB accepts the conditional DDL
 * MySQL refuses, a script is only ever run once by hand, and a shop in French
 * or English never notices the six other languages have no wording.
 */
final class UpdateScriptTest extends TestCase
{
    /**
     * Accepted by MariaDB, a syntax error on MySQL 8 - which stops the update
     * in the middle of the script, FOREIGN_KEY_CHECKS still at 0. The portable
     * form is the information_schema guard plus PREPARE/EXECUTE the scripts
     * already use.
     */
    private const string MARIADB_ONLY_CONDITIONAL_DDL = '/\b(?:ADD|MODIFY|CHANGE|DROP)\s+(?:COLUMN|INDEX|KEY|CONSTRAINT)?\s*IF\s+(?:NOT\s+)?EXISTS\b/i';

    /**
     * The script a released shop has not run yet, and the only one still open
     * to being fixed: the ones before it are already applied in the field.
     */
    private const string PENDING_SCRIPT = '3.1.0.sql';

    public function testNoUpdateScriptUsesConditionalDdlMySqlRefuses(): void
    {
        foreach ($this->updateScripts() as $name => $sql) {
            self::assertDoesNotMatchRegularExpression(
                self::MARIADB_ONLY_CONDITIONAL_DDL,
                $sql,
                \sprintf('%s uses ALTER ... IF [NOT] EXISTS, which only MariaDB parses.', $name),
            );
        }
    }

    public function testThePendingUpdateScriptInsertsNothingTwiceOnAReplay(): void
    {
        $statements = $this->statementsOf($this->pendingScript());
        $inserts = array_filter($statements, static fn (string $statement): bool => 1 === preg_match('/^INSERT\b/i', $statement));

        self::assertNotEmpty($inserts, 'The pending script seeds nothing: this test has lost its subject.');

        foreach ($inserts as $statement) {
            $replayable = 1 === preg_match('/^INSERT\s+IGNORE\b/i', $statement)
                || 1 === preg_match('/\bWHERE\s+NOT\s+EXISTS\b/i', $statement)
                || 1 === preg_match('/\bON\s+DUPLICATE\s+KEY\b/i', $statement);

            self::assertTrue(
                $replayable,
                \sprintf(
                    'An update that stops halfway must be replayable, and this INSERT would fail on its unique key: %s',
                    $this->firstLineOf($statement),
                ),
            );
        }
    }

    #[DataProvider('translatedReferenceTables')]
    public function testThePendingUpdateScriptSeedsTheLocalesTheFreshInstallSeeds(string $table): void
    {
        $expected = $this->localesSeededIn($this->freshInstallSeed(), $table);

        self::assertGreaterThan(2, \count($expected), \sprintf('The fresh install seeds %s in fewer locales than expected.', $table));

        self::assertSame(
            $expected,
            $this->localesSeededIn($this->pendingScript(), $table),
            \sprintf(
                'A shop updated to this version would display %s with no label at all in the missing languages.',
                $table,
            ),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function translatedReferenceTables(): iterable
    {
        yield 'order_return_status_i18n' => ['order_return_status_i18n'];
        yield 'order_return_reason_i18n' => ['order_return_reason_i18n'];
        yield 'message_i18n' => ['message_i18n'];
        yield 'resource_i18n' => ['resource_i18n'];
    }

    /**
     * The two administration permissions the returns feature adds are shown in
     * the back office by their `resource_i18n` title. The seed takes that title
     * from `setup/I18n`, so a key missing there reaches the database as NULL
     * and the profile screen lists a permission with no name.
     */
    #[DataProvider('backOfficeResourceWording')]
    public function testTheBackOfficeWordingOfTheNewPermissionsIsTranslated(string $key): void
    {
        foreach ($this->writtenTranslationCatalogs() as $locale => $catalog) {
            self::assertArrayHasKey(
                $key,
                $catalog,
                \sprintf('setup/I18n/%s.php does not translate the back-office permission "%s".', $locale, $key),
            );
            self::assertNotSame('', trim((string) $catalog[$key]));
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function backOfficeResourceWording(): iterable
    {
        yield 'Product returns' => ['Product returns'];
        yield 'Return reasons' => ['Return reasons'];
    }

    /**
     * The wording has to reach `setup/insert.sql` too, not just the catalogs it
     * is generated from: the generated file is committed, and it stayed on the
     * revision where the two permissions had no name at all - so a fresh
     * install listed two unnamed permissions while an updated shop had them
     * translated.
     */
    #[DataProvider('backOfficeResourceWording')]
    public function testTheFreshInstallSeedCarriesTheWordingOfTheNewPermissions(string $key): void
    {
        $resourceI18n = array_values(array_filter(
            $this->statementsOf($this->freshInstallSeed()),
            static fn (string $statement): bool => 1 === preg_match('/^INSERT\s+INTO\s+`resource_i18n`/i', $statement),
        ));

        self::assertNotEmpty($resourceI18n, 'The fresh install seed no longer seeds resource_i18n.');

        self::assertStringContainsString(
            "'".$key."'",
            implode("\n", $resourceI18n),
            \sprintf('setup/insert.sql seeds the "%s" permission without a title: the profile screen shows a raw key.', $key),
        );
    }

    /**
     * A forced AUTO_INCREMENT on a table the script seeds nothing into is a
     * dump artefact: it comes from the developer database the DDL was exported
     * from, and it makes the first row of an updated shop take an id the fresh
     * install gives to the seventh.
     */
    public function testThePendingUpdateScriptForcesNoAutoIncrementOnATableItDoesNotSeed(): void
    {
        $script = $this->pendingScript();
        $seeded = $this->tablesInsertedInto($script);
        $checked = 0;

        foreach ($this->statementsOf($script) as $statement) {
            if (1 !== preg_match('/^CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`(\w+)`/i', $statement, $table)) {
                continue;
            }

            if (1 !== preg_match('/AUTO_INCREMENT\s*=\s*(\d+)/i', $statement, $start)) {
                continue;
            }

            ++$checked;

            self::assertContains(
                $table[1],
                $seeded,
                \sprintf('The script starts `%s` at AUTO_INCREMENT=%s without inserting a single row into it.', $table[1], $start[1]),
            );
        }

        self::assertGreaterThan(0, $checked, 'No forced AUTO_INCREMENT left to judge: this test has lost its subject.');
    }

    /**
     * The tables the given script writes rows into.
     *
     * @return list<string>
     */
    private function tablesInsertedInto(string $sql): array
    {
        $tables = [];

        foreach ($this->statementsOf($sql) as $statement) {
            if (1 === preg_match('/^INSERT\s+(?:IGNORE\s+)?INTO\s+`(\w+)`/i', $statement, $matches)) {
                $tables[] = $matches[1];
            }
        }

        return array_values(array_unique($tables));
    }

    /**
     * @return array<string, string>
     */
    private function updateScripts(): array
    {
        $scripts = [];

        foreach (glob($this->setupDirectory().'/update/sql/*.sql') ?: [] as $path) {
            $scripts[basename($path)] = (string) file_get_contents($path);
        }

        self::assertNotEmpty($scripts);

        return $scripts;
    }

    /**
     * The catalogs of the locales the seed both creates a language for and
     * ships wording in. `setup/I18n` holds nineteen files, the seed creates
     * eight languages, and two of those eight - cs_CZ and it_IT - carry no
     * wording at all: their rows are seeded NULL on purpose.
     *
     * @return array<string, array<string, string>>
     */
    private function writtenTranslationCatalogs(): array
    {
        $catalogs = [];

        foreach (['de_DE', 'en_US', 'es_ES', 'fr_FR', 'nl_NL', 'ru_RU'] as $locale) {
            $catalogs[$locale] = include $this->setupDirectory().'/I18n/'.$locale.'.php';
        }

        return $catalogs;
    }

    private function pendingScript(): string
    {
        return (string) file_get_contents($this->setupDirectory().'/update/sql/'.self::PENDING_SCRIPT);
    }

    private function freshInstallSeed(): string
    {
        return (string) file_get_contents($this->setupDirectory().'/insert.sql');
    }

    /**
     * The locales an INSERT into the given table writes a row for.
     *
     * @return list<string>
     */
    private function localesSeededIn(string $sql, string $table): array
    {
        $locales = [];

        foreach ($this->statementsOf($sql) as $statement) {
            if (1 !== preg_match('/^INSERT\s+(?:IGNORE\s+)?INTO\s+`'.preg_quote($table, '/').'`/i', $statement)) {
                continue;
            }

            preg_match_all("/'([a-z]{2}_[A-Z]{2})'/", $statement, $matches);
            $locales = [...$locales, ...$matches[1]];
        }

        $locales = array_values(array_unique($locales));
        sort($locales);

        return $locales;
    }

    /**
     * The statements of a script, comments stripped. Naive on purpose: the
     * scripts hold no semicolon inside a string literal, and a stored routine
     * would need a DELIMITER the scripts never use.
     *
     * @return list<string>
     */
    private function statementsOf(string $sql): array
    {
        $withoutComments = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;

        $statements = [];

        foreach (explode(';', $withoutComments) as $statement) {
            $statement = trim($statement);

            if ('' !== $statement) {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    private function firstLineOf(string $statement): string
    {
        return trim(strtok($statement, "\n") ?: $statement);
    }

    private function setupDirectory(): string
    {
        return \dirname(__DIR__, 3).'/setup';
    }
}
