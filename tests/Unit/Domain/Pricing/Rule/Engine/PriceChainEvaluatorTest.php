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

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Pricing\Rule\Engine\EffectType;
use Thelia\Domain\Pricing\Rule\Engine\PriceChainEvaluator;
use Thelia\Domain\Pricing\Rule\Engine\RuleEffect;
use Thelia\Tests\Support\Taxation\FlatRateTaxCalculator;

/**
 * The chain applies the rules covering a sale element by priority, each on what
 * the previous one left, and stops where a rule asks it to. Prices are untaxed
 * in and out; the effects apply on the taxed price, with a 20% tax here.
 */
final class PriceChainEvaluatorTest extends TestCase
{
    public function testNoRuleLeavesTheCatalogPriceStanding(): void
    {
        self::assertNull((new PriceChainEvaluator())->evaluate([], 100.0, new FlatRateTaxCalculator()));
    }

    public function testAPercentageComesOffTheTaxedPrice(): void
    {
        // 100 HT -> 120 TTC, minus 20% -> 96 TTC -> 80 HT
        $result = (new PriceChainEvaluator())->evaluate(
            [$this->effect(1, 100, EffectType::Percentage, 20.0)],
            100.0,
            new FlatRateTaxCalculator(),
        );

        self::assertNotNull($result);
        self::assertEqualsWithDelta(80.0, $result->untaxedPrice, 0.000001);
        self::assertSame(1, $result->ruleId);
        self::assertSame([1], $result->appliedRuleIds);
        self::assertFalse($result->wasClamped());
    }

    public function testAnAmountComesOffTheTaxedPriceInTheCurrency(): void
    {
        // 120 TTC minus 12 -> 108 TTC -> 90 HT
        $result = (new PriceChainEvaluator())->evaluate(
            [$this->effect(1, 100, EffectType::Amount, 12.0)],
            100.0,
            new FlatRateTaxCalculator(),
        );

        self::assertEqualsWithDelta(90.0, $result->untaxedPrice, 0.000001);
    }

    public function testAFixedPriceIsATaxedPrice(): void
    {
        // 9.90 TTC -> 8.25 HT
        $result = (new PriceChainEvaluator())->evaluate(
            [$this->effect(1, 100, EffectType::FixedPrice, 9.90)],
            100.0,
            new FlatRateTaxCalculator(),
        );

        self::assertEqualsWithDelta(8.25, $result->untaxedPrice, 0.000001);
    }

    /**
     * Recette 3 of the ticket: 9.90 fixed on the brand, with the stop flag and the
     * smaller priority, wins over 10% off the whole shop - 9.90, not 8.91.
     */
    public function testTheSmallerPriorityAppliesFirstAndTheStopFlagEndsTheChain(): void
    {
        $result = (new PriceChainEvaluator())->evaluate(
            [
                $this->effect(2, 100, EffectType::Percentage, 10.0),
                $this->effect(1, 10, EffectType::FixedPrice, 9.90, stop: true),
            ],
            100.0,
            new FlatRateTaxCalculator(),
        );

        self::assertEqualsWithDelta(8.25, $result->untaxedPrice, 0.000001);
        self::assertSame([1], $result->appliedRuleIds);
        self::assertSame(1, $result->ruleId);
    }

    public function testWithoutTheStopFlagTheNextRuleAppliesOnTopOfThePrevious(): void
    {
        // 9.90 TTC then 10% off -> 8.91 TTC -> 7.425 HT
        $result = (new PriceChainEvaluator())->evaluate(
            [
                $this->effect(2, 100, EffectType::Percentage, 10.0),
                $this->effect(1, 10, EffectType::FixedPrice, 9.90),
            ],
            100.0,
            new FlatRateTaxCalculator(),
        );

        self::assertEqualsWithDelta(7.425, $result->untaxedPrice, 0.000001);
        self::assertSame([1, 2], $result->appliedRuleIds);
        self::assertSame(2, $result->ruleId, 'the last rule that applied is the one the price is attributed to');
    }

    /**
     * Two rules of the same priority come in ascending id order, whatever order
     * they were handed in: the order is announced, not left to the database.
     */
    public function testRulesOfTheSamePriorityApplyByAscendingId(): void
    {
        $effects = [
            $this->effect(7, 50, EffectType::FixedPrice, 60.0, stop: true),
            $this->effect(3, 50, EffectType::FixedPrice, 24.0, stop: true),
        ];

        $forward = (new PriceChainEvaluator())->evaluate($effects, 100.0, new FlatRateTaxCalculator());
        $backward = (new PriceChainEvaluator())->evaluate(array_reverse($effects), 100.0, new FlatRateTaxCalculator());

        self::assertSame(3, $forward->ruleId);
        self::assertSame(3, $backward->ruleId);
        self::assertEqualsWithDelta(20.0, $forward->untaxedPrice, 0.000001);
    }

    public function testAPriceThatWouldGoNegativeIsFlooredAtZeroAndReported(): void
    {
        $result = (new PriceChainEvaluator())->evaluate(
            [$this->effect(4, 100, EffectType::Amount, 500.0)],
            100.0,
            new FlatRateTaxCalculator(),
        );

        self::assertSame(0.0, $result->untaxedPrice);
        self::assertTrue($result->wasClamped());
        self::assertSame([4], $result->clampedRuleIds);
    }

    public function testTheCatalogPriceStaysVisibleWhenAnyAppliedRuleAsksForIt(): void
    {
        $result = (new PriceChainEvaluator())->evaluate(
            [
                $this->effect(1, 10, EffectType::Percentage, 10.0, displayInitialPrice: false),
                $this->effect(2, 20, EffectType::Percentage, 10.0, displayInitialPrice: true),
            ],
            100.0,
            new FlatRateTaxCalculator(),
        );

        self::assertTrue($result->displayInitialPrice);
    }

    private function effect(
        int $id,
        int $priority,
        EffectType $type,
        float $value,
        bool $stop = false,
        bool $displayInitialPrice = false,
    ): RuleEffect {
        return new RuleEffect($id, $priority, $stop, $type, $value, $displayInitialPrice);
    }
}
