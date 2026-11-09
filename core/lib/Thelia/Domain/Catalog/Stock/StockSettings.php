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

use Propel\Runtime\Propel;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ConfigTableMap;

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

    /** Written once a hold dated a line: '1' from then on, absent on a shop that never held one. */
    public const RESERVATION_HAS_HELD = 'stock_reservation_has_held';

    public const DEFAULT_RESERVATION_DURATION_MINUTES = 15;
    public const DEFAULT_LOW_STOCK_THRESHOLD = 5.0;

    /** How many reservation durations a line may stay held from its first hold. */
    public const LONGEST_HOLD_IN_DURATIONS = 4;

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

    /**
     * Minutes a line may stay held from its first hold, however often it is held again: past them,
     * holding again no longer pushes the date back, and the stock goes back on sale once the date
     * runs out. A buyer going back and forth in the checkout stays well within it.
     */
    public function longestHoldMinutes(): int
    {
        return self::LONGEST_HOLD_IN_DURATIONS * $this->reservationDurationMinutes();
    }

    /**
     * Minutes a line whose date ran out must stay untouched before a hold takes it as a new one
     * (a new longest hold, a token of the budget): long enough for other carts to buy the unit.
     * A buyer who held once and comes back later is always held for at least a duration again.
     */
    public function lapsedHoldMinutes(): int
    {
        return 2 * $this->reservationDurationMinutes();
    }

    /**
     * Whether a line may still carry the date of a hold: the reservation was turned on and held a
     * line at least once, whatever it is now. Read from the configuration cache, no query: a shop
     * that never reserved answers no, and the lines held before the reservation was turned off are
     * still released, and told, when they go.
     */
    public function linesMayBeHeld(): bool
    {
        $stored = ConfigQuery::read(self::RESERVATION_HAS_HELD, '0');

        return \is_string($stored) && is_numeric($stored) && 0 !== (int) $stored;
    }

    /**
     * Written before the first hold writes a date; nothing is written once the cache says so. Two
     * first holds at once both find the row missing: it is created by whichever comes first, and
     * written as any variable is (which drops the shared cache of the configuration) by both.
     */
    public function rememberThatLinesAreHeld(): void
    {
        if ($this->linesMayBeHeld()) {
            return;
        }

        $statement = Propel::getWriteConnection(ConfigTableMap::DATABASE_NAME)->prepare(
            'INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES (:name, \'0\', 1, 1, NOW(), NOW()) '
            .'ON DUPLICATE KEY UPDATE `id` = `id`',
        );
        $statement->bindValue(':name', self::RESERVATION_HAS_HELD);
        $statement->execute();
        ConfigTableMap::clearInstancePool();

        ConfigQuery::write(self::RESERVATION_HAS_HELD, '1');
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
