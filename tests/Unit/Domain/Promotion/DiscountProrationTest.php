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

namespace Thelia\Tests\Unit\Domain\Promotion;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Promotion\Coupon\Service\DiscountProration;

/**
 * The stored cart discount is the authoritative figure: what the promotions
 * price is scaled onto it, and whatever rounding leaves over lands on the last
 * line, so the listed amounts always add up to the stored discount to the cent.
 */
final class DiscountProrationTest extends TestCase
{
    public function testAmountsMatchingTheTargetAreKeptAsTheyAre(): void
    {
        self::assertSame([5.0, 3.0], DiscountProration::prorate([5.0, 3.0], 8.0));
    }

    public function testAmountsAreScaledOntoTheTarget(): void
    {
        self::assertSame([2.5, 1.5], DiscountProration::prorate([5.0, 3.0], 4.0));
    }

    public function testTheRoundingRemainderLandsOnTheLastLine(): void
    {
        $prorated = DiscountProration::prorate([5.0, 5.0, 5.0], 10.0);

        self::assertSame([3.33, 3.33, 3.34], $prorated);
        self::assertSame(10.0, round(array_sum($prorated), 2));
    }

    public function testASingleAmountBecomesTheTargetItself(): void
    {
        self::assertSame([3.0], DiscountProration::prorate([5.0], 3.0));
    }

    public function testAZeroTargetDistributesNothing(): void
    {
        self::assertSame([], DiscountProration::prorate([5.0, 3.0], 0.0));
    }

    public function testANegativeTargetDistributesNothing(): void
    {
        self::assertSame([], DiscountProration::prorate([5.0], -1.0));
    }

    public function testNoAmountsToDistributeOverYieldsNothing(): void
    {
        self::assertSame([], DiscountProration::prorate([], 5.0));
        self::assertSame([], DiscountProration::prorate([0.0, 0.0], 5.0));
    }

    public function testTheFactorIsTheScaleBetweenPricedAndStored(): void
    {
        self::assertSame(0.5, DiscountProration::factor([5.0, 3.0], 4.0));
    }

    public function testTheFactorIsZeroWhenThereIsNothingToScale(): void
    {
        self::assertSame(0.0, DiscountProration::factor([], 4.0));
        self::assertSame(0.0, DiscountProration::factor([5.0], 0.0));
    }
}
