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
use Thelia\Domain\Promotion\Coupon\Type\BuyXGetY;

/**
 * The inputs of a coupon type are posted under the coupon form and its dataset, the
 * names the back office reads the effects back from.
 */
final class CouponFieldNameTest extends TestCase
{
    public function testACouponFieldIsNamedAfterTheCouponFormAndItsDataset(): void
    {
        $translator = $this->createStub(Translator::class);

        $facade = $this->createStub(FacadeInterface::class);
        $facade->method('getTranslator')->willReturn($translator);
        $facade->method('getConditionEvaluator')->willReturn(new ConditionEvaluator());

        $makeCouponFieldName = new \ReflectionMethod(BuyXGetY::class, 'makeCouponFieldName');

        self::assertSame(
            'thelia_coupon_creation[coupon_specific][amount]',
            $makeCouponFieldName->invoke(new BuyXGetY($facade), 'amount'),
        );
    }
}
