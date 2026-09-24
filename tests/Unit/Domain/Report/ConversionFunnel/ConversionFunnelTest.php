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

namespace Thelia\Tests\Unit\Domain\Report\ConversionFunnel;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Report\ConversionFunnel\ConversionFunnel;
use Thelia\Domain\Report\ConversionFunnel\DailyFunnelRow;
use Thelia\Domain\Report\ConversionFunnel\FunnelStep;

final class ConversionFunnelTest extends TestCase
{
    public function testTheStepsComeInTheOrderOfTheFunnel(): void
    {
        $funnel = ConversionFunnel::fromCounts(20, 8, 6, 5, 4, 2);

        self::assertSame(
            [
                FunnelStep::CARTS_CREATED,
                FunnelStep::CARTS_WITH_ITEMS,
                FunnelStep::CARTS_WITH_DELIVERY,
                FunnelStep::CARTS_WITH_PAYMENT,
                FunnelStep::ORDERS_CREATED,
                FunnelStep::ORDERS_PAID,
            ],
            array_map(static fn (FunnelStep $step): string => $step->key, $funnel->steps),
        );
        self::assertSame([20, 8, 6, 5, 4, 2], array_map(static fn (FunnelStep $step): int => $step->count, $funnel->steps));
    }

    public function testEachStepIsComparedToThePreviousOneAndToTheCartsWithItems(): void
    {
        $funnel = ConversionFunnel::fromCounts(20, 8, 6, 5, 4, 2);

        self::assertSame(
            [null, 20, 8, 6, 5, 4],
            array_map(static fn (FunnelStep $step): ?int => $step->previousCount, $funnel->steps),
        );
        self::assertSame([8, 8, 8, 8, 8, 8], array_map(static fn (FunnelStep $step): int => $step->baseCount, $funnel->steps));
        self::assertSame(40.0, $funnel->step(FunnelStep::CARTS_WITH_ITEMS)->percentToPrevious());
        self::assertSame(75.0, $funnel->step(FunnelStep::CARTS_WITH_DELIVERY)->percentToBase());
    }

    public function testTheConversionRateIsThePaidOrdersOverTheCartsWithItems(): void
    {
        $funnel = ConversionFunnel::fromCounts(20, 8, 6, 5, 4, 2);

        self::assertSame(0.25, $funnel->conversionRate());
        self::assertSame(25.0, $funnel->conversionRatePercent());
    }

    public function testThereIsNoConversionRateWithoutACartWithItems(): void
    {
        $funnel = ConversionFunnel::fromCounts(3, 0, 0, 0, 1, 1);

        self::assertNull($funnel->conversionRate());
        self::assertNull($funnel->conversionRatePercent());
        foreach ($funnel->steps as $step) {
            self::assertNull($step->ratioToBase(), $step->key);
        }
    }

    public function testTheConversionRateIsRoundedToOneDecimal(): void
    {
        self::assertSame(33.3, ConversionFunnel::fromCounts(9, 3, 3, 3, 1, 1)->conversionRatePercent());
    }

    public function testAnUnknownStepIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConversionFunnel::fromCounts(1, 1, 1, 1, 1, 1)->step('nope');
    }

    public function testADailyRowGivesItsConversionRate(): void
    {
        $day = new \DateTimeImmutable('2026-09-01');

        self::assertSame(12.5, (new DailyFunnelRow($day, 10, 8, 4, 2, 1, 1))->conversionRatePercent());
        self::assertNull((new DailyFunnelRow($day, 10, 0, 0, 0, 1, 1))->conversionRatePercent());
    }
}
