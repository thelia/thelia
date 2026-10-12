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

namespace Thelia\Domain\Shipping\DeliveryDate\Service;

/**
 * The days of the week nobody delivers on, as stored: ISO numbers from 1 (Monday) to
 * 7 (Sunday) separated by commas.
 *
 * The shop keeps its own list in the `delivery_closed_weekdays` configuration entry, and a
 * carrier rule may replace it with its own. An empty list is a list — every day open — and
 * is stored as an empty string, which is what tells it from a carrier that follows the shop
 * (NULL).
 */
final class ClosedWeekdays
{
    public const string SHOP_CONFIG_NAME = 'delivery_closed_weekdays';

    /**
     * @return list<int>
     */
    public static function parse(?string $stored): array
    {
        if (null === $stored) {
            return [];
        }

        $days = [];

        foreach (explode(',', $stored) as $part) {
            $part = trim($part);

            if (1 === preg_match('/^[1-7]$/', $part)) {
                $days[(int) $part] = (int) $part;
            }
        }

        ksort($days);

        return array_values($days);
    }

    /**
     * @param iterable<int|string> $days
     */
    public static function format(iterable $days): string
    {
        $kept = [];

        foreach ($days as $day) {
            $day = (int) $day;

            if ($day >= 1 && $day <= 7) {
                $kept[$day] = $day;
            }
        }

        ksort($kept);

        return implode(',', $kept);
    }
}
