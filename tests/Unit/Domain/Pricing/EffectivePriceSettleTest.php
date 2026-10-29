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

namespace Thelia\Tests\Unit\Domain\Pricing;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Pricing\EffectivePrice;

/**
 * The one merge of the catalog price and the effective price, which every reader goes through.
 */
final class EffectivePriceSettleTest extends TestCase
{
    public function testARulePriceReplacesThePromoPriceAndLeavesTheCatalogPrice(): void
    {
        $settled = (new EffectivePrice(1, 80.0, true, EffectivePrice::SOURCE_RULE))->settle(100.0);

        self::assertSame(100.0, $settled->untaxedPrice);
        self::assertSame(80.0, $settled->untaxedPromoPrice);
        self::assertTrue($settled->isPromo);
        self::assertTrue($settled->displayInitialPrice);
        self::assertFalse($settled->priceReplaced);
    }

    public function testABundlePriceReplacesTheCatalogPrice(): void
    {
        $settled = (new EffectivePrice(1, 540.0, true, EffectivePrice::SOURCE_BUNDLE, untaxedPrice: '600.000000'))->settle(999.0);

        self::assertSame(600.0, $settled->untaxedPrice);
        self::assertSame(540.0, $settled->untaxedPromoPrice);
        self::assertTrue($settled->isPromo);
        self::assertTrue($settled->priceReplaced);
    }

    public function testABundleWithoutDiscountIsNotOnPromotion(): void
    {
        $price = new EffectivePrice(1, 600.0, false, EffectivePrice::SOURCE_BUNDLE, untaxedPrice: '600.000000');

        self::assertFalse($price->isPromo());
        self::assertFalse($price->settle(0.0)->isPromo);
        self::assertFalse($price->settle(0.0)->displayInitialPrice);
    }

    public function testTheCustomerDiscountIsAppliedToWhatTheEffectivePriceSaysAndNotToTheCatalogPrice(): void
    {
        $rule = (new EffectivePrice(1, 80.0, true, EffectivePrice::SOURCE_RULE))->settle(95.0, 10.0);
        $bundle = (new EffectivePrice(1, 540.0, true, EffectivePrice::SOURCE_BUNDLE, untaxedPrice: '600'))->settle(0.0, 10.0);

        self::assertSame(95.0, $rule->untaxedPrice, 'Read with its own discount already.');
        self::assertEqualsWithDelta(72.0, $rule->untaxedPromoPrice, 0.000001);
        self::assertEqualsWithDelta(540.0, $bundle->untaxedPrice, 0.000001);
        self::assertEqualsWithDelta(486.0, $bundle->untaxedPromoPrice, 0.000001);
    }

    public function testTheSavingOfABundleIsPublishedByThePriceUntaxedAndTaxed(): void
    {
        $settled = (new EffectivePrice(1, 540.0, true, EffectivePrice::SOURCE_BUNDLE, untaxedPrice: '600'))->settle(0.0);

        self::assertSame(60.0, $settled->untaxedSaving());
        // 20 % of tax: 720 struck through, 648 charged.
        self::assertSame(72.0, $settled->taxedSaving(static fn (float $untaxed): float => $untaxed * 1.2));
    }

    public function testTheSavingIsTheCustomerDiscountedOne(): void
    {
        $settled = (new EffectivePrice(1, 540.0, true, EffectivePrice::SOURCE_BUNDLE, untaxedPrice: '600'))->settle(0.0, 10.0);

        self::assertEqualsWithDelta(54.0, $settled->untaxedSaving(), 0.000001);
    }

    public function testThereIsNoSavingWhenNothingIsStruckThrough(): void
    {
        $noDiscount = (new EffectivePrice(1, 600.0, false, EffectivePrice::SOURCE_BUNDLE, untaxedPrice: '600'))->settle(0.0);
        $hidden = (new EffectivePrice(1, 540.0, false, EffectivePrice::SOURCE_BUNDLE, untaxedPrice: '600'))->settle(0.0);

        self::assertNull($noDiscount->untaxedSaving());
        self::assertNull($noDiscount->taxedSaving(static fn (float $untaxed): float => $untaxed * 1.2));
        self::assertNull($hidden->untaxedSaving(), 'The shop asks not to show the price without the offer.');
    }
}
