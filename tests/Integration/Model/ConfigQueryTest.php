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

namespace Thelia\Tests\Integration\Model;

use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

final class ConfigQueryTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    /**
     * Locks the falsy-value pitfall together with the query count: a stored
     * '0' read twice must still be served from the cache the second time,
     * not just return the right value at the cost of a query per call.
     */
    public function testReadingAStoredZeroTwiceCostsOneQuery(): void
    {
        ConfigQuery::write('test_read_falsy_zero_query_count', '0');
        ConfigQuery::resetCache();

        $statements = $this->recordSqlQueries(static function (): void {
            self::assertSame('0', ConfigQuery::read('test_read_falsy_zero_query_count', 1));
            self::assertSame('0', ConfigQuery::read('test_read_falsy_zero_query_count', 1));
        });

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'config'),
            'Reading the same name twice must load the table once, not query per call.',
        );
    }

    /**
     * The snapshot loaded on the first uncached read is exhaustive: a name
     * that was never written has no row in the table at all, so every read
     * of it after the first one must be free, and several distinct never
     * written names must still share the same single bulk load.
     */
    public function testReadingSeveralNeverWrittenNamesCostsAtMostOneQuery(): void
    {
        ConfigQuery::resetCache();

        $statements = $this->recordSqlQueries(static function (): void {
            self::assertSame('a', ConfigQuery::read('test_never_written_one', 'a'));
            self::assertSame('b', ConfigQuery::read('test_never_written_two', 'b'));
            self::assertSame('b', ConfigQuery::read('test_never_written_two', 'b'));
            self::assertSame('c', ConfigQuery::read('test_never_written_three', 'c'));
        });

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'config'),
            'Four reads across three never-written names must still cost a single bulk load.',
        );
    }

    public function testReadReturnsAStoredZeroInsteadOfTheDefault(): void
    {
        ConfigQuery::write('test_read_falsy_zero', '0');
        ConfigQuery::resetCache();

        self::assertSame('0', ConfigQuery::read('test_read_falsy_zero', 1));
    }

    public function testReadReturnsAStoredEmptyStringInsteadOfTheDefault(): void
    {
        ConfigQuery::write('test_read_falsy_empty', '');
        ConfigQuery::resetCache();

        self::assertSame('', ConfigQuery::read('test_read_falsy_empty', 'fallback'));
    }

    public function testReadStillReturnsTheDefaultForAnUnknownName(): void
    {
        ConfigQuery::resetCache();

        self::assertSame('fallback', ConfigQuery::read('test_read_unknown_name', 'fallback'));
    }

    public function testCheckAvailableStockHonoursAZeroWrittenFromTheBackOffice(): void
    {
        ConfigQuery::write('check-available-stock', '0');

        self::assertFalse(ConfigQuery::checkAvailableStock());
    }

    /**
     * Guards the isolation the test above relies on: the value it writes is rolled
     * back, and booting the kernel rebuilds the cache from the database.
     */
    public function testTheCacheDoesNotKeepAValueRolledBackByAPreviousTest(): void
    {
        self::assertTrue(ConfigQuery::checkAvailableStock());
    }
}
