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

namespace Thelia\Domain\Checkout\Enum;

/**
 * Where a shop lets an express payment button appear.
 *
 * Only in the checkout: the buyer has already been identified and has chosen where the
 * parcel goes and how, and the wallet only pays. A wallet that skipped the checkout would
 * have to pick the carrier in its own sheet, which cannot offer a pickup point. The
 * merchant turns the zone on or off.
 */
enum ExpressPaymentZone: string
{
    /**
     * The payment step of the checkout, in both display modes: the wallet is listed among
     * the payment methods, and choosing it puts its button in the place of the order button.
     * The wallet takes the payment and nothing else: the addresses and the carrier are those
     * of the cart.
     */
    case Checkout = 'checkout';

    /**
     * The zones a stored setting names.
     *
     * An unknown zone is dropped rather than refused: a setting written by a newer Thelia
     * degrades to the zones this version knows instead of hiding every button. An empty
     * setting means no zone at all, which is a shop with no express payment — the value a
     * shop that upgrades is given, so nothing appears where nothing appeared before.
     *
     * @return list<self>
     */
    public static function listFromStoredValue(?string $value): array
    {
        $named = array_filter(array_map(
            static fn (string $entry): ?self => self::tryFrom(strtolower(trim($entry))),
            explode(',', (string) $value)
        ));

        return array_values(array_filter(self::cases(), static fn (self $zone): bool => \in_array($zone, $named, true)));
    }

    /**
     * The value stored for a list of zones.
     *
     * @param list<self> $zones
     */
    public static function toStoredValue(array $zones): string
    {
        return implode(',', array_map(
            static fn (self $zone): string => $zone->value,
            array_values(array_filter(self::cases(), static fn (self $zone): bool => \in_array($zone, $zones, true)))
        ));
    }
}
