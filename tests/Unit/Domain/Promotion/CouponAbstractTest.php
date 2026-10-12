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
 * What every coupon type inherits: the texts it was set with, the plain amount
 * it grants when the type does not price anything itself, and the id of the
 * coupon row it was built from.
 */
final class CouponAbstractTest extends TestCase
{
    public function testTheTextsOfTheCouponAreKeptAsGiven(): void
    {
        $coupon = $this->couponSetWith(
            [],
            title: 'Summer sale',
            shortDescription: 'Ten euros off',
            description: 'Ten euros off any order this summer.',
        );

        self::assertSame('Summer sale', $coupon->getTitle());
        self::assertSame('Ten euros off', $coupon->getShortDescription());
        self::assertSame('Ten euros off any order this summer.', $coupon->getDescription());
    }

    public function testACouponIsGrantedTheAmountOfItsEffects(): void
    {
        self::assertSame(12.5, $this->couponSetWith([CouponAbstract::AMOUNT_FIELD_NAME => '12.5'])->exec());
    }

    public function testACouponWithoutAmountGrantsNothing(): void
    {
        self::assertSame(0.0, $this->couponSetWith([])->exec());
    }

    public function testTheIdOfTheCouponRowItWasBuiltFromIsExposed(): void
    {
        $coupon = $this->couponSetWith([])->setCouponModelId(12);

        self::assertSame(12, $coupon->getCouponModelId());
    }

    /**
     * @param array<string, mixed> $effects
     */
    private function couponSetWith(
        array $effects,
        ?string $title = null,
        ?string $shortDescription = null,
        ?string $description = null,
    ): CouponAbstract {
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
            $title,
            $shortDescription,
            $description,
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
