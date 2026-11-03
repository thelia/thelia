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

namespace Thelia\Tests\Integration\Domain\Promotion;

use Thelia\Domain\Promotion\Coupon\Exception\InvalidCouponExclusionException;
use Thelia\Domain\Promotion\Coupon\Service\CouponExclusionWriter;
use Thelia\Model\CouponExclusionQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * An exclusion goes both ways: when A never applies with B, B never applies with A.
 */
final class CouponExclusionWriterTest extends IntegrationTestCase
{
    private CouponExclusionWriter $writer;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->writer = $this->getService(CouponExclusionWriter::class);
    }

    public function testExcludingAPromotionExcludesItBothWays(): void
    {
        $first = $this->factory->coupon()->getId();
        $second = $this->factory->coupon()->getId();

        $this->writer->replace($first, [$second]);

        self::assertSame([$second], CouponExclusionQuery::create()->excludedCouponIdsFor($first));
        self::assertSame([$first], CouponExclusionQuery::create()->excludedCouponIdsFor($second));
    }

    public function testReplacingRemovesTheExistingPairsInBothDirections(): void
    {
        $first = $this->factory->coupon()->getId();
        $second = $this->factory->coupon()->getId();
        $third = $this->factory->coupon()->getId();

        $this->writer->replace($first, [$second]);
        $this->writer->replace($first, [$third]);

        self::assertSame([$third], CouponExclusionQuery::create()->excludedCouponIdsFor($first));
        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($second));
        self::assertSame([$first], CouponExclusionQuery::create()->excludedCouponIdsFor($third));

        $this->writer->replace($first, []);

        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($first));
        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($third));
    }

    public function testDuplicatedIdsAreWrittenOnce(): void
    {
        $first = $this->factory->coupon()->getId();
        $second = $this->factory->coupon()->getId();

        $this->writer->replace($first, [$second, $second, (string) $second]);

        self::assertSame([$second], CouponExclusionQuery::create()->excludedCouponIdsFor($first));
    }

    public function testAPromotionCannotExcludeItself(): void
    {
        $first = $this->factory->coupon()->getId();
        $second = $this->factory->coupon()->getId();
        $third = $this->factory->coupon()->getId();
        $this->writer->replace($first, [$third]);

        try {
            $this->writer->replace($first, [$second, $first]);
            self::fail('A promotion excluding itself must be refused.');
        } catch (InvalidCouponExclusionException) {
        }

        self::assertSame([$third], CouponExclusionQuery::create()->excludedCouponIdsFor($first), 'The pair written before survives the refusal.');
        self::assertSame([$first], CouponExclusionQuery::create()->excludedCouponIdsFor($third));
        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($second));
    }

    public function testAnUnknownPromotionIsRefusedAndNothingIsWritten(): void
    {
        $first = $this->factory->coupon()->getId();
        $second = $this->factory->coupon()->getId();
        $third = $this->factory->coupon()->getId();
        $this->writer->replace($first, [$second]);

        $unknown = $third + 100000;

        try {
            $this->writer->replace($first, [$third, $unknown]);
            self::fail('An unknown promotion must be refused.');
        } catch (InvalidCouponExclusionException $exception) {
            self::assertStringContainsString((string) $unknown, $exception->getMessage());
        }

        self::assertSame([$second], CouponExclusionQuery::create()->excludedCouponIdsFor($first));
        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($third));
    }

    public function testDeletingAPromotionCleansBothDirections(): void
    {
        $first = $this->factory->coupon();
        $second = $this->factory->coupon()->getId();
        $this->writer->replace($first->getId(), [$second]);

        $first->delete();

        self::assertSame([], CouponExclusionQuery::create()->excludedCouponIdsFor($second));
        self::assertSame(0, CouponExclusionQuery::create()->filterByExcludedCouponId($first->getId())->count());
    }

    public function testQueriesAreRestrictedToTheCandidatesAndGrouped(): void
    {
        $first = $this->factory->coupon()->getId();
        $second = $this->factory->coupon()->getId();
        $third = $this->factory->coupon()->getId();
        $outsider = $this->factory->coupon()->getId();
        $this->writer->replace($first, [$second, $third, $outsider]);

        $query = CouponExclusionQuery::create();

        self::assertSame([$second, $third, $outsider], $query->excludedCouponIdsFor($first));
        self::assertSame(
            [$first => [$second, $third], $second => [$first], $third => [$first]],
            $query->excludedCouponIdsAmong([$first, $second, $third]),
        );
        self::assertSame([], $query->excludedCouponIdsAmong([]));
        self::assertSame(
            [$first => 3, $second => 1],
            $query->countByCoupons([$first, $second, $this->factory->coupon()->getId()]),
        );
        self::assertSame([], $query->countByCoupons([]));
    }
}
