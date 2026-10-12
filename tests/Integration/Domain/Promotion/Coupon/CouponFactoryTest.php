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

namespace Thelia\Tests\Integration\Domain\Promotion\Coupon;

use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\MatchForEveryone;
use Thelia\Domain\Promotion\Coupon\CouponFactory;
use Thelia\Domain\Promotion\Coupon\Exception\CouponExpiredException;
use Thelia\Domain\Promotion\Coupon\Exception\CouponNotReleaseException;
use Thelia\Domain\Promotion\Coupon\Exception\InactiveCouponException;
use Thelia\Model\Coupon;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * A typed code is built into a coupon only when it can apply right now:
 * known, enabled, started and not expired.
 */
final class CouponFactoryTest extends ActionIntegrationTestCase
{
    private CouponFactory $couponFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->couponFactory = $this->getService(CouponFactory::class);
    }

    public function testAnUnknownCodeIsRefusedAsInactive(): void
    {
        $this->expectException(InactiveCouponException::class);

        $this->couponFactory->buildCouponFromCode('UNKNOWN-'.uniqid());
    }

    public function testADisabledCodeIsRefusedAsInactive(): void
    {
        $coupon = $this->codedCoupon(['isEnabled' => false]);

        $this->expectException(InactiveCouponException::class);

        $this->couponFactory->buildCouponFromCode($coupon->getCode());
    }

    public function testACodeWhoseStartDateIsToComeIsRefused(): void
    {
        $coupon = $this->codedCoupon(['startDate' => new \DateTime('+1 day')]);

        $this->expectException(CouponNotReleaseException::class);

        $this->couponFactory->buildCouponFromCode($coupon->getCode());
    }

    public function testAnExpiredCodeIsRefused(): void
    {
        $coupon = $this->codedCoupon(['expirationDate' => new \DateTime('-1 day')]);

        $this->expectException(CouponExpiredException::class);

        $this->couponFactory->buildCouponFromCode($coupon->getCode());
    }

    public function testACodeStartedInThePastIsBuilt(): void
    {
        $coupon = $this->codedCoupon(['startDate' => new \DateTime('-1 day')]);

        self::assertSame($coupon->getCode(), $this->couponFactory->buildCouponFromCode($coupon->getCode())->getCode());
    }

    public function testACodeWithNoStartDateIsBuilt(): void
    {
        $coupon = $this->codedCoupon();

        self::assertSame($coupon->getCode(), $this->couponFactory->buildCouponFromCode($coupon->getCode())->getCode());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function codedCoupon(array $overrides = []): Coupon
    {
        $conditions = new ConditionCollection();
        $conditions[] = $this->getService(MatchForEveryone::class);

        return $this->factory->coupon(array_merge([
            'code' => 'CODE-'.uniqid(),
            'conditions' => $this->getService(ConditionFactory::class)->serializeConditionCollection($conditions),
        ], $overrides));
    }
}
