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

namespace Thelia\Tests\Unit\Domain\Catalog\Stock;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Catalog\Stock\OutOfStockPolicy;
use Thelia\Domain\Catalog\Stock\PreorderRule;

final class PreorderRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{float, float, float, OutOfStockPolicy, bool}>
     */
    public static function quantities(): iterable
    {
        yield 'a mode that refuses never preorders' => [5.0, 0.0, 0.0, OutOfStockPolicy::Refuse, false];
        yield 'covered by the stock' => [3.0, 3.0, 0.0, OutOfStockPolicy::Limited, false];
        yield 'one unit beyond the stock' => [4.0, 3.0, 0.0, OutOfStockPolicy::Limited, true];
        yield 'three in stock, five asked on a limit of ten' => [5.0, 3.0, 0.0, OutOfStockPolicy::Limited, true];
        yield 'what other carts hold leaves nothing' => [2.0, 3.0, 3.0, OutOfStockPolicy::Unlimited, true];
        yield 'what other carts hold leaves enough' => [2.0, 5.0, 3.0, OutOfStockPolicy::Unlimited, false];
        yield 'a stock below zero counts as none' => [1.0, -2.0, 0.0, OutOfStockPolicy::Limited, true];
        yield 'a reservation below zero counts as none' => [3.0, 3.0, -4.0, OutOfStockPolicy::Unlimited, false];
        yield 'nothing asked is no preorder' => [0.0, 0.0, 0.0, OutOfStockPolicy::Unlimited, false];
        yield 'the last 0.3 kg once other carts hold 0.4 of 0.7' => [0.3, 0.7, 0.4, OutOfStockPolicy::Limited, false];
        yield 'a gram beyond what the other carts leave' => [0.301, 0.7, 0.4, OutOfStockPolicy::Limited, true];
    }

    #[DataProvider('quantities')]
    public function testAQuantityIsAPreorderWhenItGoesBeyondWhatTheOtherCartsLeave(float $asked, float $physical, float $reserved, OutOfStockPolicy $policy, bool $expected): void
    {
        self::assertSame($expected, PreorderRule::isPreorder($asked, $physical, $reserved, $policy));
    }

    public function testTheNextUnitIsAPreorderOnceTheOtherCartsHoldTheStock(): void
    {
        self::assertFalse(PreorderRule::nextUnitIsPreorder(1.0, 0.0, OutOfStockPolicy::Limited));
        self::assertTrue(PreorderRule::nextUnitIsPreorder(1.0, 1.0, OutOfStockPolicy::Limited));
        self::assertTrue(PreorderRule::nextUnitIsPreorder(0.0, 0.0, OutOfStockPolicy::Unlimited));
        self::assertFalse(PreorderRule::nextUnitIsPreorder(0.0, 0.0, OutOfStockPolicy::Refuse));
    }

    public function testTheNextUnitOfAQuantitySoldByMeasureIsAPreorderOnlyOnceNothingPhysicalIsLeft(): void
    {
        self::assertFalse(PreorderRule::nextUnitIsPreorder(0.5, 0.0, OutOfStockPolicy::Limited), 'Half a kilogram is still on the shelf.');
        self::assertFalse(PreorderRule::nextUnitIsPreorder(0.7, 0.4, OutOfStockPolicy::Limited), 'Other carts hold 0.4 of 0.7: 0.3 is left.');
        self::assertTrue(PreorderRule::nextUnitIsPreorder(0.7, 0.7, OutOfStockPolicy::Limited));
        self::assertTrue(PreorderRule::nextUnitIsPreorder(0.3, 0.1 + 0.2, OutOfStockPolicy::Limited), 'Two holds that add up to the stock leave nothing.');
    }
}
