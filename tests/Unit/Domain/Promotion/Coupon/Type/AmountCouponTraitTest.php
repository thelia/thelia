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

/**
 * The amount of a fixed amount coupon, as the back office stores it.
 */
final class AmountCouponTraitTest extends TestCase
{
    public function testAnAmountStoredAsTextIsGrantedAsANumber(): void
    {
        $facade = $this->createStub(FacadeInterface::class);
        $facade->method('getTranslator')->willReturn($this->createStub(Translator::class));
        $facade->method('getConditionEvaluator')->willReturn(new ConditionEvaluator());
        $facade->method('getCartTotalTaxPrice')->willReturn(100.0);

        $coupon = (new RemoveXAmount($facade))->set(
            $facade,
            'TWELVE',
            'Twelve and a half off',
            '',
            '',
            [CouponAbstract::AMOUNT_FIELD_NAME => '12.5'],
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

        self::assertSame(12.5, $coupon->exec());
    }
}
