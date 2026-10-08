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
 * The progress trail of the checkout reads the title of each step in the language of the
 * page, without going through a translation catalogue. A language the shop ships but the
 * seed leaves out draws the trail in the shop language instead. A fresh install names the
 * four steps in German as an updated shop does.
 */
final class CheckoutStepSeedTest extends TestCase
{
    private const string UPDATE_SCRIPT = '3.3.0.sql';

    private const array GERMAN = [
        1 => 'Ihr Warenkorb',
        2 => 'Lieferung',
        3 => 'Zahlung',
        4 => 'Bestätigung',
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function freshInstallSeeds(): iterable
    {
        yield 'generated seed' => ['insert.sql'];
        yield 'seed template' => ['insert.sql.tpl'];
    }

    #[DataProvider('freshInstallSeeds')]
    public function testTheFreshInstallNamesTheFourStepsInGerman(string $seed): void
    {
        $sql = (string) file_get_contents($this->setupDirectory().'/'.$seed);
        $start = strpos($sql, 'INSERT INTO `checkout_step_i18n`');
        self::assertNotFalse($start, \sprintf('%s no longer seeds the titles of the checkout steps.', $seed));
        $statement = substr($sql, $start, (int) strpos($sql, "\n;", $start) - $start);

        preg_match_all("/\((\d), 'de_DE', '((?:[^']|'')+)'\)/", $statement, $rows, \PREG_SET_ORDER);
        $german = [];

        foreach ($rows as [, $id, $title]) {
            $german[(int) $id] = $title;
        }

        self::assertSame(self::GERMAN, $german);
    }

    public function testTheUpdateNamesTheStepsLikeTheFreshInstall(): void
    {
        $sql = (string) file_get_contents($this->setupDirectory().'/update/sql/'.self::UPDATE_SCRIPT);
        $statements = array_map('trim', explode(';', preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql));
        $german = array_values(array_filter($statements, static fn (string $statement): bool => 1 === preg_match('/^INSERT\s+IGNORE\s+INTO\s+`checkout_step_i18n`\s/i', $statement)));

        self::assertCount(1, $german, \sprintf('%s should add the German titles of the checkout steps in one statement.', self::UPDATE_SCRIPT));
        preg_match_all("/SELECT '([a-z]+)'(?: AS `code`)?, '((?:[^']|'')+)'/", $german[0], $rows, \PREG_SET_ORDER);

        self::assertSame(
            ['cart' => self::GERMAN[1], 'delivery' => self::GERMAN[2], 'payment' => self::GERMAN[3], 'confirmation' => self::GERMAN[4]],
            array_combine(array_column($rows, 1), array_column($rows, 2)),
        );
    }

    private function setupDirectory(): string
    {
        return \dirname(__DIR__, 3).'/setup';
    }
}
