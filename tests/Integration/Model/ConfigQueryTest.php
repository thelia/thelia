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

final class ConfigQueryTest extends IntegrationTestCase
{
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
