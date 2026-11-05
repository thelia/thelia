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

namespace Thelia\Tests\Integration\Domain\Catalog\Stock;

use Thelia\Domain\Catalog\Stock\StockReservationScope;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

final class StockSettingsTest extends IntegrationTestCase
{
    private const KEYS = [
        StockSettings::RESERVATION_ENABLED,
        StockSettings::RESERVATION_SCOPE,
        StockSettings::RESERVATION_DURATION,
        StockSettings::LOW_STOCK_THRESHOLD,
    ];

    protected function tearDown(): void
    {
        parent::tearDown();

        // The rollback leaves the static cache of ConfigQuery on the values a case wrote.
        foreach (self::KEYS as $key) {
            ConfigQuery::read($key, null, true);
        }
    }

    public function testAFreshInstallHoldsTheDocumentedDefaults(): void
    {
        $settings = new StockSettings();

        self::assertFalse($settings->reservationEnabled());
        self::assertSame(StockReservationScope::Checkout, $settings->reservationScope());
        self::assertSame(15, $settings->reservationDurationMinutes());
        self::assertSame(5.0, $settings->lowStockThreshold());
    }

    public function testAStoredZeroIsAValueNotAnAbsence(): void
    {
        ConfigQuery::write(StockSettings::LOW_STOCK_THRESHOLD, '0');
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '0');

        $settings = new StockSettings();

        self::assertSame(0.0, $settings->lowStockThreshold());
        self::assertFalse($settings->reservationEnabled());
    }

    public function testTheReservationCanBeTurnedOnAndMovedToTheCart(): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'cart');
        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '30');
        ConfigQuery::write(StockSettings::LOW_STOCK_THRESHOLD, '12.5');

        $settings = new StockSettings();

        self::assertTrue($settings->reservationEnabled());
        self::assertSame(StockReservationScope::Cart, $settings->reservationScope());
        self::assertSame(30, $settings->reservationDurationMinutes());
        self::assertSame(12.5, $settings->lowStockThreshold());
    }

    public function testAMalformedValueFallsBackOnTheDefault(): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'basket');
        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '0');
        ConfigQuery::write(StockSettings::LOW_STOCK_THRESHOLD, 'many');

        $settings = new StockSettings();

        self::assertSame(StockReservationScope::Checkout, $settings->reservationScope());
        self::assertSame(15, $settings->reservationDurationMinutes());
        self::assertSame(5.0, $settings->lowStockThreshold());

        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '-3');
        ConfigQuery::write(StockSettings::LOW_STOCK_THRESHOLD, '-1');

        self::assertSame(15, $settings->reservationDurationMinutes());
        self::assertSame(5.0, $settings->lowStockThreshold());
    }
}
