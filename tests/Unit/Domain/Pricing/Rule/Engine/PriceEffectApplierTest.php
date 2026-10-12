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

namespace Thelia\Tests\Unit\Domain\Pricing\Rule\Engine;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Pricing\Rule\Engine\EffectType;
use Thelia\Domain\Pricing\Rule\Engine\PriceEffectApplier;
use Thelia\Domain\Pricing\Rule\Engine\RuleEffect;
use Thelia\Tests\Support\Taxation\FlatRateTaxCalculator;

/**
 * A price that lands exactly on zero is a free product, not a negative one: only an
 * effect that goes past zero is reported as floored. Prices are untaxed, with a 20%
 * tax, so 100 untaxed is 120 taxed.
 */
final class PriceEffectApplierTest extends TestCase
{
    /**
     * @return iterable<string, array{EffectType, float, float, bool}>
     */
    public static function effectsAroundZero(): iterable
    {
        yield 'a hundred percent off makes the product free' => [EffectType::Percentage, 100.0, 0.0, false];
        yield 'more than a hundred percent off goes below zero' => [EffectType::Percentage, 100.5, 0.0, true];
        yield 'an amount equal to the taxed price makes the product free' => [EffectType::Amount, 120.0, 0.0, false];
        yield 'an amount above the taxed price goes below zero' => [EffectType::Amount, 120.01, 0.0, true];
        yield 'an ordinary fixed price' => [EffectType::FixedPrice, 9.90, 8.25, false];
        yield 'a fixed price of zero gives the product away' => [EffectType::FixedPrice, 0.0, 0.0, false];
        yield 'a negative fixed price goes below zero' => [EffectType::FixedPrice, -5.0, 0.0, true];
    }

    #[DataProvider('effectsAroundZero')]
    public function testThePriceIsFlooredAtZeroAndOnlyAnEffectGoingPastItIsReported(EffectType $type, float $value, float $untaxedPrice, bool $floored): void
    {
        $applier = new PriceEffectApplier();
        $effect = $this->effect($type, $value);

        self::assertSame($floored, $applier->wouldGoNegative(100.0, $effect, new FlatRateTaxCalculator()));
        self::assertEqualsWithDelta($untaxedPrice, $applier->apply(100.0, $effect, new FlatRateTaxCalculator()), 0.000001);
    }

    private function effect(EffectType $type, float $value): RuleEffect
    {
        return new RuleEffect(1, 100, false, $type, $value, false);
    }
}
