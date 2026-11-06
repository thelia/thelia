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

namespace Thelia\Domain\Catalog\Stock;

use Thelia\Model\ConfigQuery;

/**
 * The shop-wide stock settings, read from the configuration.
 *
 * A stored '0' is a value, never an absence: each reader compares the number,
 * not the truthiness of the string, and only a missing or malformed value falls
 * back on the default.
 */
final readonly class StockSettings
{
    public const RESERVATION_ENABLED = 'stock_reservation_enabled';
    public const RESERVATION_SCOPE = 'stock_reservation_scope';
    public const RESERVATION_DURATION = 'stock_reservation_duration';
    public const LOW_STOCK_THRESHOLD = 'low_stock_threshold';

    public const DEFAULT_RESERVATION_DURATION_MINUTES = 15;
    public const DEFAULT_LOW_STOCK_THRESHOLD = 5.0;

    public function reservationEnabled(): bool
    {
        $stored = ConfigQuery::read(self::RESERVATION_ENABLED, '0');

        return \is_string($stored) && is_numeric($stored) && 0 !== (int) $stored;
    }

    public function reservationScope(): StockReservationScope
    {
        $stored = ConfigQuery::read(self::RESERVATION_SCOPE, StockReservationScope::Checkout->value);

        return \is_string($stored) ? (StockReservationScope::tryFrom($stored) ?? StockReservationScope::Checkout) : StockReservationScope::Checkout;
    }

    /** Minutes a reservation is held; a value that is not a whole number above zero gives the default. */
    public function reservationDurationMinutes(): int
    {
        $stored = ConfigQuery::read(self::RESERVATION_DURATION, (string) self::DEFAULT_RESERVATION_DURATION_MINUTES);

        if (!\is_string($stored) || !ctype_digit($stored) || (int) $stored < 1) {
            return self::DEFAULT_RESERVATION_DURATION_MINUTES;
        }

        return (int) $stored;
    }

    /** The threshold of the shop; 0 is a valid value, a missing or negative one gives the default. */
    public function lowStockThreshold(): float
    {
        $stored = ConfigQuery::read(self::LOW_STOCK_THRESHOLD, (string) self::DEFAULT_LOW_STOCK_THRESHOLD);

        if (!\is_string($stored) || !is_numeric($stored) || (float) $stored < 0) {
            return self::DEFAULT_LOW_STOCK_THRESHOLD;
        }

        return (float) $stored;
    }
}
