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

namespace Thelia\Tests\Unit\Domain\Cart;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Cart\Service\CartPurgeHorizon;
use Thelia\Model\ConfigQuery;

final class CartPurgeHorizonTest extends TestCase
{
    #[DataProvider('retentionDaysCases')]
    public function testRetentionDaysIsTheMinimumOfBothPeriods(int $noOrderDays, int $anonymousDays, int $expected): void
    {
        $horizon = new CartPurgeHorizon($noOrderDays, $anonymousDays);

        self::assertSame($expected, $horizon->retentionDays());
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function retentionDaysCases(): iterable
    {
        yield 'no order days is smaller' => [30, 60, 30];
        yield 'anonymous days is smaller' => [10, 60, 10];
    }

    public function testEarliestSurvivingCartDateIsRetentionDaysBeforeNowToTheSecond(): void
    {
        $horizon = new CartPurgeHorizon(60, 30);
        $now = new \DateTimeImmutable('2026-09-24 15:00:00');

        self::assertEquals(
            new \DateTimeImmutable('2026-08-25 15:00:00'),
            $horizon->earliestSurvivingCartDate($now)
        );
    }

    public function testAPeriodStartingAtMidnightOnTheHorizonDayMayHaveLostCarts(): void
    {
        $horizon = new CartPurgeHorizon(60, 30);
        $now = new \DateTimeImmutable('2026-09-24 15:00:00');

        self::assertTrue(
            $horizon->mayHavePurged(new \DateTimeImmutable('2026-08-25 00:00:00'), $now),
            'The purge deletes the carts created before now - 30 days: those of 2026-08-25 00:00 to 15:00 are gone.',
        );
    }

    public function testMayHavePurgedIsFalseWhenFromIsExactlyTheEarliestSurvivingDate(): void
    {
        $horizon = new CartPurgeHorizon(60, 30);
        $now = new \DateTimeImmutable('2026-09-24 15:00:00');
        $earliest = $horizon->earliestSurvivingCartDate($now);

        self::assertFalse($horizon->mayHavePurged($earliest, $now));
    }

    public function testMayHavePurgedIsTrueWhenFromIsOneSecondBeforeTheEarliestSurvivingDate(): void
    {
        $horizon = new CartPurgeHorizon(60, 30);
        $now = new \DateTimeImmutable('2026-09-24 15:00:00');
        $earliest = $horizon->earliestSurvivingCartDate($now);

        self::assertTrue($horizon->mayHavePurged($earliest->modify('-1 second'), $now));
    }

    public function testMayHavePurgedIsFalseWhenFromIsInsideTheRetentionWindow(): void
    {
        $horizon = new CartPurgeHorizon(60, 30);
        $now = new \DateTimeImmutable('2026-09-24 15:00:00');

        self::assertFalse($horizon->mayHavePurged($now->modify('-1 day'), $now));
    }

    public function testConstructorRejectsANegativeCartNoOrderDays(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CartPurgeHorizon(-1, 30);
    }

    public function testConstructorRejectsANegativeCartAnonymousDays(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CartPurgeHorizon(30, -1);
    }

    public function testFromConfigReadsANegativeRetentionAsZero(): void
    {
        ConfigQuery::initCache([
            CartPurgeHorizon::CONFIG_KEY_CART_NO_ORDER_DAYS => '-5',
            CartPurgeHorizon::CONFIG_KEY_CART_ANONYMOUS_DAYS => '-1',
        ]);

        try {
            $horizon = CartPurgeHorizon::fromConfig();
        } finally {
            ConfigQuery::resetCache();
        }

        self::assertSame(0, $horizon->cartNoOrderDays());
        self::assertSame(0, $horizon->cartAnonymousDays());
        self::assertSame(0, $horizon->retentionDays());
    }

    public function testFromConfigKeepsBothRetentions(): void
    {
        ConfigQuery::initCache([
            CartPurgeHorizon::CONFIG_KEY_CART_NO_ORDER_DAYS => '90',
            CartPurgeHorizon::CONFIG_KEY_CART_ANONYMOUS_DAYS => '15',
        ]);

        try {
            $horizon = CartPurgeHorizon::fromConfig();
        } finally {
            ConfigQuery::resetCache();
        }

        self::assertSame(90, $horizon->cartNoOrderDays());
        self::assertSame(15, $horizon->cartAnonymousDays());
    }

    public function testAStartBeforeTheHorizonIsMovedToTheHorizon(): void
    {
        $horizon = new CartPurgeHorizon(60, 30);
        $now = new \DateTimeImmutable('2026-09-24 15:00:00');

        self::assertEquals(
            new \DateTimeImmutable('2026-08-25 15:00:00'),
            $horizon->boundedStart(new \DateTimeImmutable('2026-01-01 00:00:00'), $now),
        );
    }

    public function testAStartInsideTheRetentionIsKept(): void
    {
        $horizon = new CartPurgeHorizon(60, 30);
        $now = new \DateTimeImmutable('2026-09-24 15:00:00');
        $from = new \DateTimeImmutable('2026-09-20 00:00:00');

        self::assertEquals($from, $horizon->boundedStart($from, $now));
    }

    public function testConstantsMatchTheLiteralConfigKeys(): void
    {
        self::assertSame('purification_cart_no_order_days', CartPurgeHorizon::CONFIG_KEY_CART_NO_ORDER_DAYS);
        self::assertSame('purification_cart_anonymous_days', CartPurgeHorizon::CONFIG_KEY_CART_ANONYMOUS_DAYS);
    }
}
