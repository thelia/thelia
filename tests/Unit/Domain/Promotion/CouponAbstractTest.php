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

namespace Thelia\Tests\Unit\Domain\Promotion;

use PHPUnit\Framework\TestCase;
use Thelia\Condition\ConditionEvaluator;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\Type\CouponAbstract;

/**
 * What every coupon type inherits from the values it was set with.
 */
final class CouponAbstractTest extends TestCase
{
    public function testTheExpirationDateIsReturnedAsGiven(): void
    {
        self::assertEquals(new \DateTime('2030-01-01'), $this->couponSetWith([])->getExpirationDate());
    }

    /**
     * @param array<string, mixed> $effects
     */
    private function couponSetWith(array $effects): CouponAbstract
    {
        $translator = $this->createStub(Translator::class);

        $facade = $this->createStub(FacadeInterface::class);
        $facade->method('getTranslator')->willReturn($translator);
        $facade->method('getConditionEvaluator')->willReturn(new ConditionEvaluator());

        $coupon = new class($facade) extends CouponAbstract {
            public function getName(): string
            {
                return 'Plain amount';
            }

            public function getToolTip(): string
            {
                return 'Takes a plain amount off the cart.';
            }
        };

        return $coupon->set(
            $facade,
            'SUMMER',
            null,
            null,
            null,
            $effects,
            true,
            false,
            false,
            true,
            -1,
            new \DateTime('2030-01-01'),
            [],
            [],
            false,
        );
    }
}
