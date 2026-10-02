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

namespace Thelia\Tests\Unit\Domain\Checkout;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Checkout\Enum\ExpressPaymentZone;

/**
 * Where a shop allows an express payment button.
 *
 * The setting is a comma-separated string in the `config` table, which a merchant may
 * edit by hand and a newer Thelia may write zones into that this version has never heard
 * of. The rule that matters: what is not understood is dropped, and a shop left with
 * nothing shows no button anywhere — the state every shop is in until it says otherwise.
 */
final class ExpressPaymentZoneTest extends TestCase
{
    public function testAShopEnablesNoZoneUntilItSaysSo(): void
    {
        self::assertSame([], ExpressPaymentZone::listFromStoredValue(null));
        self::assertSame([], ExpressPaymentZone::listFromStoredValue(''));
        self::assertSame([], ExpressPaymentZone::listFromStoredValue('   '));
    }

    /**
     * A setting written by another version may name zones this one does not know: they are
     * dropped, and the zones this version knows are kept.
     */
    public function testAnUnknownZoneIsDroppedAndTheRestIsKept(): void
    {
        self::assertSame([], ExpressPaymentZone::listFromStoredValue('cart,product'));
        self::assertSame([ExpressPaymentZone::Checkout], ExpressPaymentZone::listFromStoredValue('cart,checkout'));
    }

    public function testEntriesAreReadCaseInsensitivelyAndTrimmed(): void
    {
        self::assertSame([ExpressPaymentZone::Checkout], ExpressPaymentZone::listFromStoredValue(' Checkout '));
    }

    public function testAZoneListedTwiceIsKeptOnce(): void
    {
        self::assertSame([ExpressPaymentZone::Checkout], ExpressPaymentZone::listFromStoredValue('checkout,checkout'));
    }

    public function testAListSurvivesARoundTripThroughTheStoredValue(): void
    {
        $stored = ExpressPaymentZone::toStoredValue([ExpressPaymentZone::Checkout]);

        self::assertSame('checkout', $stored);
        self::assertSame([ExpressPaymentZone::Checkout], ExpressPaymentZone::listFromStoredValue($stored));
        self::assertSame('', ExpressPaymentZone::toStoredValue([]));
    }

    /**
     * No zone may be named "0": the shop settings, the forms and the templates all read
     * "0" as off, and a zone that answers false to half the code reading it is a bug
     * waiting for whoever adds the next zone.
     */
    public function testNoZoneIsNamedZero(): void
    {
        foreach (ExpressPaymentZone::cases() as $zone) {
            self::assertNotSame('0', $zone->value);
            self::assertNotSame('', $zone->value);
        }
    }
}
