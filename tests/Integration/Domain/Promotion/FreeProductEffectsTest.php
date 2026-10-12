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

use Thelia\Domain\Promotion\Coupon\CouponFactory;
use Thelia\Domain\Promotion\Coupon\Type\FreeProduct;

/**
 * The effects of a coupon are free JSON: the API writes them as they come, so
 * a free product coupon may be stored without the product it offers.
 */
final class FreeProductEffectsTest extends ActionIntegrationPromotionTestCase
{
    public function testAFreeProductCouponStoredWithoutItsOfferedProductIsRebuiltWithoutAWarning(): void
    {
        $coupon = $this->factory->coupon([
            'code' => 'FREEPROD-'.uniqid(),
            'type' => 'thelia.coupon.type.free_product',
            'effects' => ['products' => [$this->product()->getId()], 'category_id' => 0],
        ]);

        $warnings = [];
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        }, \E_WARNING);

        try {
            $freeProduct = $this->getService(CouponFactory::class)->buildCouponFromModel($coupon);
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $warnings);
        self::assertInstanceOf(FreeProduct::class, $freeProduct);
    }
}
