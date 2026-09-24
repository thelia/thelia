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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Report\ConversionFunnel\FunnelStep;

final class FunnelStepTest extends TestCase
{
    public function testTheFirstStepHasNoRatioToAPreviousStep(): void
    {
        $step = new FunnelStep(FunnelStep::CARTS_CREATED, 20, null, 10);

        self::assertNull($step->ratioToPrevious());
        self::assertNull($step->percentToPrevious());
        self::assertSame(2.0, $step->ratioToBase());
        self::assertSame(200.0, $step->percentToBase());
    }

    public function testAnEmptyStepConvertsNothing(): void
    {
        $step = new FunnelStep(FunnelStep::ORDERS_PAID, 0, 10, 10);

        self::assertSame(0.0, $step->ratioToPrevious());
        self::assertSame(0.0, $step->percentToPrevious());
        self::assertSame(0.0, $step->ratioToBase());
        self::assertSame(0.0, $step->percentToBase());
    }

    public function testAStepEqualToThePreviousOneConvertsEverything(): void
    {
        $step = new FunnelStep(FunnelStep::CARTS_WITH_DELIVERY, 10, 10, 10);

        self::assertSame(1.0, $step->ratioToPrevious());
        self::assertSame(100.0, $step->percentToPrevious());
        self::assertSame(1.0, $step->ratioToBase());
        self::assertSame(100.0, $step->percentToBase());
    }

    public function testAnEmptyPreviousStepHasNoRatio(): void
    {
        $step = new FunnelStep(FunnelStep::CARTS_WITH_PAYMENT, 0, 0, 5);

        self::assertNull($step->ratioToPrevious());
        self::assertNull($step->percentToPrevious());
        self::assertSame(0.0, $step->ratioToBase());
    }

    public function testAnEmptyBaseHasNoRatio(): void
    {
        $step = new FunnelStep(FunnelStep::CARTS_CREATED, 4, null, 0);

        self::assertNull($step->ratioToBase());
        self::assertNull($step->percentToBase());
    }

    #[DataProvider('roundedPercentages')]
    public function testPercentagesAreRoundedToOneDecimal(int $count, int $previous, float $expected): void
    {
        $step = new FunnelStep(FunnelStep::ORDERS_CREATED, $count, $previous, $previous);

        self::assertSame($expected, $step->percentToPrevious());
        self::assertSame($expected, $step->percentToBase());
    }

    /**
     * @return iterable<string, array{int, int, float}>
     */
    public static function roundedPercentages(): iterable
    {
        yield 'one third' => [1, 3, 33.3];
        yield 'two thirds' => [2, 3, 66.7];
        yield 'one eighth' => [1, 8, 12.5];
        yield 'seven ninths' => [7, 9, 77.8];
    }
}
