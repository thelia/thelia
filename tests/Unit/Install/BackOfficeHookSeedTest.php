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
 * A module listens to a hook only if the hook exists in the `hook` table: the
 * container build drops any other listener ("Hook ... is unknown."), without a
 * visible error. The hooks the back-office templates call have to be seeded on a
 * fresh install, and added by the update to a shop installed before them.
 */
final class BackOfficeHookSeedTest extends TestCase
{
    private const int BACK_OFFICE = 2;

    private const string UPDATE_SCRIPT = '3.2.1.sql';

    /**
     * The back-office hooks the seed declared up to 3.2.0 end at 1426; the range goes on to 1999 (2000 and up hold
     * the PDF and e-mail hooks, and a few back-office hooks of older releases).
     */
    private const int FIRST_ADDED_ID = 1427;

    private const int LAST_BACK_OFFICE_ID = 1999;

    /**
     * The extension points the back-office customer sheet documents for modules.
     *
     * @return iterable<string, array{string, int}>
     */
    public static function customerSheetHooks(): iterable
    {
        yield 'section of the customer sheet' => ['customer.tab', 1];
        yield 'card in the Modules section' => ['customer.tab-content', 0];
        yield 'button next to the sheet navigation' => ['customer-edit.actions', 0];
    }

    #[DataProvider('customerSheetHooks')]
    public function testTheFreshInstallSeedsTheHooksTheCustomerSheetOffersToModules(string $code, int $block): void
    {
        $hooks = $this->hooksSeededBy($this->freshInstallSeed());

        self::assertArrayHasKey($code, $hooks, \sprintf('setup/insert.sql does not seed the back-office hook %s.', $code));
        self::assertSame($block, $hooks[$code]);
    }

    /**
     * A shop updated to this version has the same back-office hooks as a fresh install: the update adds every hook
     * the seed declares after the ones the previous releases shipped, and only those.
     */
    public function testTheUpdateAddsTheBackOfficeHooksTheFreshInstallSeeds(): void
    {
        $added = $this->hooksAddedByTheUpdate();
        $seeded = $this->hooksSeededBy($this->freshInstallSeed(), self::FIRST_ADDED_ID, self::LAST_BACK_OFFICE_ID);

        self::assertArrayHasKey('customer.tab', $added, 'The update no longer adds the back-office hooks: this test has lost its subject.');

        ksort($added);
        ksort($seeded);
        self::assertSame($seeded, $added, 'The update and the fresh install do not add the same back-office hooks, or not with the same block flag.');

        $titled = $this->hooksTitledByTheUpdate();
        ksort($titled);
        self::assertSame(array_keys($added), array_keys($titled), 'The update gives a title to other hooks than the ones it adds.');
    }

    /**
     * A module may have declared one of these hooks with an id of its own, and an
     * update stopped halfway is run again: the hook goes in only when no
     * back-office hook has its code, a title only for a language it has none in.
     */
    public function testTheUpdateAddsAHookAndItsTitlesOnlyWhenMissing(): void
    {
        [$hooks, $titles] = $this->hookStatementsOfTheUpdate();

        self::assertMatchesRegularExpression('/WHERE\s+NOT\s+EXISTS\s*\(\s*SELECT\s+1\s+FROM\s+`hook`\s+WHERE\s+`hook`\.`code`\s*=\s*`missing`\.`code`\s+AND\s+`hook`\.`type`\s*=\s*2\s*\)/i', $hooks);
        self::assertMatchesRegularExpression('/WHERE\s+NOT\s+EXISTS\s*\(\s*SELECT\s+1\s+FROM\s+`hook_i18n`\s+WHERE\s+`hook_i18n`\.`id`\s*=\s*`hook`\.`id`\s+AND\s+`hook_i18n`\.`locale`\s*=\s*`lang`\.`locale`\s*\)/i', $titles);
        self::assertDoesNotMatchRegularExpression('/INSERT\s+INTO\s+`hook`\s*\(\s*`id`/i', $hooks, 'A fixed id would collide with a hook a module created.');
    }

    /**
     * @return array<string, int> code => block flag
     */
    private function hooksSeededBy(string $sql, int $fromId = 0, int $toId = \PHP_INT_MAX): array
    {
        preg_match_all("/^\((\d+), '([a-z0-9_.-]+)', (\d), \d, (\d),/m", $sql, $rows, \PREG_SET_ORDER);
        $hooks = [];

        foreach ($rows as [, $id, $code, $type, $block]) {
            if (self::BACK_OFFICE === (int) $type && (int) $id >= $fromId && (int) $id <= $toId) {
                $hooks[$code] = (int) $block;
            }
        }

        return $hooks;
    }

    /**
     * @return array<string, int> code => block flag
     */
    private function hooksAddedByTheUpdate(): array
    {
        preg_match_all("/SELECT '([a-z0-9_.-]+)'(?: AS `code`)?, (\d)/", $this->hookStatementsOfTheUpdate()[0], $rows, \PREG_SET_ORDER);

        return array_combine(array_column($rows, 1), array_map(intval(...), array_column($rows, 2)));
    }

    /**
     * @return array<string, string> code => English title
     */
    private function hooksTitledByTheUpdate(): array
    {
        preg_match_all("/SELECT '([a-z0-9_.-]+)'(?: AS `code`)?, '((?:[^']|'')+)'/", $this->hookStatementsOfTheUpdate()[1], $rows, \PREG_SET_ORDER);

        return array_combine(array_column($rows, 1), array_column($rows, 2));
    }

    /**
     * The INSERT into `hook` and the INSERT into `hook_i18n` of the update.
     *
     * @return array{string, string}
     */
    private function hookStatementsOfTheUpdate(): array
    {
        $sql = (string) file_get_contents($this->setupDirectory().'/update/sql/'.self::UPDATE_SCRIPT);
        $statements = array_map('trim', explode(';', preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql));
        $hooks = array_values(array_filter($statements, static fn (string $statement): bool => 1 === preg_match('/^INSERT\s+INTO\s+`hook`\s/i', $statement)));
        $titles = array_values(array_filter($statements, static fn (string $statement): bool => 1 === preg_match('/^INSERT\s+INTO\s+`hook_i18n`\s/i', $statement)));

        self::assertCount(1, $hooks, \sprintf('%s should add the back-office hooks in one statement.', self::UPDATE_SCRIPT));
        self::assertCount(1, $titles, \sprintf('%s should add the titles of the back-office hooks in one statement.', self::UPDATE_SCRIPT));

        return [$hooks[0], $titles[0]];
    }

    private function freshInstallSeed(): string
    {
        return (string) file_get_contents($this->setupDirectory().'/insert.sql');
    }

    private function setupDirectory(): string
    {
        return \dirname(__DIR__, 3).'/setup';
    }
}
