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

namespace Thelia\Tests\Api\Front;

use Thelia\Model\Coupon;
use Thelia\Test\ApiTestCase;

/**
 * How a promotion is triggered is part of what a client reads about it: a
 * promotion that applies on its own has no code to ask the customer for.
 */
final class CouponTriggerModeApiTest extends ApiTestCase
{
    public function testACodedCouponReportsItsTriggerMode(): void
    {
        $factory = $this->createFixtureFactory();
        $coupon = $factory->coupon(['code' => 'TRIGGER-'.uniqid()]);

        $data = $this->readCoupon($coupon->getId());

        self::assertSame(Coupon::TRIGGER_MODE_CODE, $data['triggerMode']);
        self::assertSame($coupon->getCode(), $data['code']);
    }

    public function testAnAutomaticPromotionReportsItsTriggerModeAndNoCode(): void
    {
        $factory = $this->createFixtureFactory();
        $coupon = $factory->coupon([
            'code' => null,
            'triggerMode' => Coupon::TRIGGER_MODE_AUTOMATIC,
        ]);

        $data = $this->readCoupon($coupon->getId());

        self::assertSame(Coupon::TRIGGER_MODE_AUTOMATIC, $data['triggerMode']);
        // A null column is left out of the payload altogether.
        self::assertNull($data['code'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function readCoupon(int $couponId): array
    {
        $response = $this->jsonRequest(
            'GET',
            '/api/front/coupons/'.$couponId,
            token: $this->authenticateAsCustomer(),
        );

        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
