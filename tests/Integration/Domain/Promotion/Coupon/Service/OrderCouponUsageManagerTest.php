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

namespace Thelia\Tests\Integration\Domain\Promotion\Coupon\Service;

use Thelia\Domain\Promotion\Coupon\Service\OrderCouponUsageManager;
use Thelia\Model\Coupon;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * An order counts the usages of the coupons it was placed with, found back by
 * id, or by code when the order kept no usable id.
 */
final class OrderCouponUsageManagerTest extends ActionIntegrationTestCase
{
    /**
     * An automatic promotion has no code: once the row it came from is gone, the
     * order line points at nothing, and no other promotion without a code may
     * take the usage in its place.
     */
    public function testAnOrderLineWhosePromotionIsGoneCountsAgainstNoOtherPromotion(): void
    {
        $otherPromotion = $this->automaticPromotion();
        $gonePromotion = $this->automaticPromotion();
        $order = $this->factory->order();
        $this->factory->orderCoupon($order, $gonePromotion);
        $gonePromotion->delete();

        self::assertSame(1, $this->getService(OrderCouponUsageManager::class)->consume($order));

        $otherPromotion->reload();
        self::assertSame(5, $otherPromotion->getMaxUsage());
    }

    private function automaticPromotion(): Coupon
    {
        return $this->factory->coupon([
            'code' => null,
            'triggerMode' => Coupon::TRIGGER_MODE_AUTOMATIC,
            'maxUsage' => 5,
        ]);
    }
}
