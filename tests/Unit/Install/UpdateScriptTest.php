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
     * The reference tables whose rows a shop reads by their translated label: a row
     * seeded in fewer locales than the fresh install shows up unnamed in the back
     * office of a shop that updated.
     *
     * @var list<string>
     */
    private const array TRANSLATED_REFERENCE_TABLES = [
        'order_return_status_i18n',
        'order_return_reason_i18n',
        'message_i18n',
        'resource_i18n',
        'choice_filter_other_i18n',
    ];

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

        if ([] === $inserts) {
            self::markTestSkipped(\sprintf('%s seeds nothing yet: nothing to replay.', $this->pendingScriptName()));
        }

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

    /**
     * A release seeds the reference tables its own feature needs, not all of them,
     * so the tables to judge are the ones the pending script actually writes into.
     * Demanding a fixed list would leave this test without a subject on the first
     * release that seeds something else.
     */
    public function testThePendingUpdateScriptSeedsTheLocalesTheFreshInstallSeeds(): void
    {
        $judged = 0;

        foreach (self::TRANSLATED_REFERENCE_TABLES as $table) {
            $statements = $this->insertsInto($this->pendingScript(), $table);

            if ([] === $statements) {
                continue;
            }

            $expected = $this->localesSeededIn($this->freshInstallSeed(), $table);

            self::assertGreaterThan(2, \count($expected), \sprintf('The fresh install seeds %s in fewer locales than expected.', $table));

            // Every statement on its own, not the script as a whole: a locale another row
            // of the same table happens to seed does not give this row a label.
            foreach ($statements as $statement) {
                ++$judged;

                self::assertSame(
                    $expected,
                    $this->localesSeededIn($statement, $table),
                    \sprintf(
                        'A shop updated to this version would display %s with no label at all in the missing languages: %s',
                        $table,
                        $this->firstLineOf($statement),
                    ),
                );
            }
        }

        if (0 === $judged) {
            self::markTestSkipped(\sprintf(
                '%s seeds none of the translated reference tables (%s) yet.',
                $this->pendingScriptName(),
                implode(', ', self::TRANSLATED_REFERENCE_TABLES),
            ));
        }
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
        yield 'Order status transition override' => ['Order status transition override'];
        yield 'Catalog price rules' => ['Catalog price rules'];
        yield 'Destination restrictions' => ['Configuration destination restrictions'];
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
     * The "Purpose" column of the variables screen is `config_i18n.title`. Four
     * releases seeded a visible variable with its `config` row alone, so the
     * screen listed it with an empty purpose - on an updated shop, and on a
     * fresh install too, since `setup/insert.sql.tpl` had no title row for it.
     */
    public function testEveryVisibleVariableOfTheFreshInstallSeedHasATitle(): void
    {
        $titled = $this->variablesTitledIn($this->freshInstallSeed());
        $untitled = [];

        foreach ($this->visibleVariablesSeededIn($this->freshInstallSeed()) as $name => $id) {
            if (!\in_array($id, $titled, true)) {
                $untitled[] = $name;
            }
        }

        self::assertSame(
            [],
            $untitled,
            'setup/insert.sql seeds these variables without an en_US title, so the variables screen shows them with an empty purpose: '
            .implode(', ', $untitled)
            .'. Add their intl() row to the config_i18n block of setup/insert.sql.tpl, their wording to setup/I18n, and regenerate the seed.',
        );
    }

    /**
     * An updated shop never replays the fresh install seed: the script that
     * adds a variable has to write its titles too, and name the variable in a
     * statement of its own (the `config_title_seed` block of 3.3.0.sql shows
     * the form).
     */
    public function testThePendingUpdateScriptGivesATitleToEveryVariableItSeeds(): void
    {
        $statements = $this->statementsOf($this->pendingScript());
        $configInserts = array_filter(
            $statements,
            static fn (string $statement): bool => 1 === preg_match('/^INSERT\s+(?:IGNORE\s+)?INTO\s+`config`/i', $statement),
        );
        $otherStatements = implode("\n", array_diff_key($statements, $configInserts));
        $titledOnFreshInstall = $this->variablesTitledIn($this->freshInstallSeed());
        $freshInstallIds = $this->visibleVariablesSeededIn($this->freshInstallSeed());
        $judged = 0;

        foreach ($configInserts as $statement) {
            preg_match_all("/^\\s*\\('([^']+)',\\s*(?:'(?:[^']|'')*'|@\\w+),\\s*\\d,\\s*(\\d),\\s*NOW\\(\\)/m", $statement, $rows, \PREG_SET_ORDER);

            foreach ($rows as [, $name, $hidden]) {
                if ('0' !== $hidden) {
                    continue;
                }

                ++$judged;

                self::assertStringContainsString(
                    "'".$name."'",
                    $otherStatements,
                    \sprintf('%s seeds the variable "%s" without a title: an updated shop shows it with an empty purpose.', $this->pendingScriptName(), $name),
                );
                self::assertArrayHasKey(
                    $name,
                    $freshInstallIds,
                    \sprintf('%s seeds the variable "%s", which setup/insert.sql does not: a fresh install would not have it.', $this->pendingScriptName(), $name),
                );
                self::assertContains(
                    $freshInstallIds[$name],
                    $titledOnFreshInstall,
                    \sprintf('setup/insert.sql seeds the variable "%s" without an en_US title: a fresh install shows it with an empty purpose.', $name),
                );
            }
        }

        if (0 === $judged) {
            self::markTestSkipped(\sprintf('%s seeds no visible variable yet.', $this->pendingScriptName()));
        }
    }

    /**
     * The same, for every table the fresh install translates: a row seeded in a
     * table the shop reads through its `_i18n` companion, without that companion,
     * is a status, a reason, a message or a hook with no label at all. This test
     * judges the tables, the locale test above judges the locales of the rows.
     */
    public function testThePendingUpdateScriptTranslatesEveryTranslatedTableItSeeds(): void
    {
        $translated = [];
        foreach ($this->tablesInsertedInto($this->freshInstallSeed()) as $table) {
            if (str_ends_with($table, '_i18n')) {
                $translated[] = substr($table, 0, -\strlen('_i18n'));
            }
        }
        self::assertNotEmpty($translated, 'The fresh install seed translates nothing: this test has lost its subject.');

        $seeded = $this->tablesInsertedInto($this->pendingScript());
        $judged = 0;

        foreach ($seeded as $table) {
            if (!\in_array($table, $translated, true)) {
                continue;
            }

            ++$judged;

            self::assertContains(
                $table.'_i18n',
                $seeded,
                \sprintf('%s seeds rows of `%s` without their `%s_i18n` rows: a shop updated to this version shows them with no label at all.', $this->pendingScriptName(), $table, $table),
            );
        }

        if (0 === $judged) {
            self::markTestSkipped(\sprintf('%s seeds no translated table yet.', $this->pendingScriptName()));
        }
    }

    /**
     * A forced AUTO_INCREMENT on a table the script seeds nothing into is a
     * dump artefact: it comes from the developer database the DDL was exported
     * from, and it makes the first row of an updated shop take an id the fresh
     * install gives to the seventh.
     */
    public function testNoUpdateScriptForcesAutoIncrementOnATableItDoesNotSeed(): void
    {
        $checked = 0;

        // Every script, not only the pending one: a release that creates no table with
        // a forced AUTO_INCREMENT would otherwise leave this test without a subject,
        // and the scripts already applied in the field keep guarding the rule.
        foreach ($this->updateScripts() as $name => $script) {
            $seeded = $this->tablesInsertedInto($script);

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
                    \sprintf('%s starts `%s` at AUTO_INCREMENT=%s without inserting a single row into it.', $name, $table[1], $start[1]),
                );
            }
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
        return (string) file_get_contents($this->setupDirectory().'/update/sql/'.$this->pendingScriptName());
    }

    /**
     * The script a released shop has not run yet, and the only one still open
     * to being fixed: the ones before it are already applied in the field. It is
     * the highest version, so the tests follow each release without an edit.
     */
    private function pendingScriptName(): string
    {
        $names = array_keys($this->updateScripts());
        usort($names, static fn (string $left, string $right): int => version_compare(basename($right, '.sql'), basename($left, '.sql')));

        return $names[0];
    }

    private function freshInstallSeed(): string
    {
        return (string) file_get_contents($this->setupDirectory().'/insert.sql');
    }

    /**
     * The visible variables the fresh install seeds, name to id: the rows of
     * its INSERT into `config` whose `hidden` column is 0.
     *
     * @return array<string, int>
     */
    private function visibleVariablesSeededIn(string $seed): array
    {
        $variables = [];

        foreach ($this->insertsInto($seed, 'config') as $statement) {
            preg_match_all("/^\\s*\\((\\d+),\\s*'([^']+)',\\s*.*?,\\s*\\d,\\s*(\\d),\\s*NOW\\(\\)/m", $statement, $rows, \PREG_SET_ORDER);

            foreach ($rows as [, $id, $name, $hidden]) {
                if ('0' === $hidden) {
                    $variables[$name] = (int) $id;
                }
            }
        }

        self::assertNotEmpty($variables, 'The fresh install seed no longer seeds config: this test has lost its subject.');

        return $variables;
    }

    /**
     * The ids of the variables the seed gives a non-NULL en_US title to.
     *
     * @return list<int>
     */
    private function variablesTitledIn(string $seed): array
    {
        $ids = [];

        foreach ($this->insertsInto($seed, 'config_i18n') as $statement) {
            preg_match_all("/^\\s*\\((\\d+),\\s*'en_US',\\s*'/m", $statement, $rows, \PREG_SET_ORDER);

            foreach ($rows as [, $id]) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /**
     * The INSERT statements of a script that write rows into the given table.
     *
     * @return list<string>
     */
    private function insertsInto(string $sql, string $table): array
    {
        return array_values(array_filter(
            $this->statementsOf($sql),
            static fn (string $statement): bool => 1 === preg_match('/^INSERT\s+(?:IGNORE\s+)?INTO\s+`'.preg_quote($table, '/').'`/i', $statement),
        ));
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
        $withoutComments = preg_replace(['/^\s*--.*$/m', '#/\*.*?\*/#s'], '', $sql) ?? $sql;

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
