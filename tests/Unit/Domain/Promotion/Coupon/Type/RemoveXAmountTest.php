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

namespace Thelia\Tests\Unit\Domain\Promotion\Coupon\Type;

use PHPUnit\Framework\TestCase;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\Type\CouponAbstract;
use Thelia\Domain\Promotion\Coupon\Type\RemoveXAmount;

final class RemoveXAmountTest extends TestCase
{
    public function testTheAmountIsGrantedWithinTheCartTotal(): void
    {
        self::assertSame(20.0, $this->couponForACartOf(100.0, 20.0)->exec());
    }

    public function testAnAmountAboveTheCartTotalGrantsTheCartTotal(): void
    {
        self::assertSame(30.0, $this->couponForACartOf(30.0, 50.0)->exec());
    }

    private function couponForACartOf(float $cartTotal, float $amount): RemoveXAmount
    {
        $facade = $this->createStub(FacadeInterface::class);
        $facade->method('getTranslator')->willReturn($this->createStub(Translator::class));
        $facade->method('getConditionEvaluator')->willReturn(new ConditionEvaluator());
        $facade->method('getCartTotalTaxPrice')->willReturn($cartTotal);

        return (new RemoveXAmount($facade))->set(
            $facade,
            'FIXED',
            'Fixed amount',
            '',
            '',
            [CouponAbstract::AMOUNT_FIELD_NAME => $amount],
            true,
            false,
            false,
            true,
            -1,
            new \DateTime('+1 month'),
            [],
            [],
            false,
        );
    }
}
