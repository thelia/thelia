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
use Thelia\Domain\Pricing\Rule\Engine\PriceSegment;
use Thelia\Domain\Pricing\Rule\Engine\PriceSegmentBuilder;
use Thelia\Domain\Pricing\Rule\Engine\RuleEffect;
use Thelia\Tests\Support\Taxation\FlatRateTaxCalculator;

/**
 * Time is cut at every rule date; each period gets the price the rules running
 * over it give. What comes out is what the price table stores, so an opening or a
 * closing shows at the second without a scheduled run.
 */
final class PriceSegmentBuilderTest extends TestCase
{
    public function testARuleWithoutDatesGivesOneOpenSegment(): void
    {
        $segments = (new PriceSegmentBuilder())->build(
            [$this->effect(1, EffectType::Percentage, 20.0)],
            100.0,
            new FlatRateTaxCalculator(),
        );

        self::assertCount(1, $segments);
        self::assertNull($segments[0]->validFrom);
        self::assertNull($segments[0]->validUntil);
        self::assertEqualsWithDelta(80.0, $segments[0]->result->untaxedPrice, 0.000001);
    }

    /**
     * Recette 1 of the ticket: 20% off in January. Before and after, no segment -
     * the catalog price stands - and the segment ends at midnight on February 1st,
     * excluded.
     */
    public function testADatedRuleGivesOneSegmentOverItsWindowOnly(): void
    {
        $segments = (new PriceSegmentBuilder())->build(
            [$this->effect(1, EffectType::Percentage, 20.0, '2027-01-01 00:00:00', '2027-02-01 00:00:00')],
            100.0,
            new FlatRateTaxCalculator(),
        );

        self::assertCount(1, $segments);
        self::assertSame('2027-01-01 00:00:00', $segments[0]->validFrom?->format('Y-m-d H:i:s'));
        self::assertSame('2027-02-01 00:00:00', $segments[0]->validUntil?->format('Y-m-d H:i:s'));
        self::assertTrue($segments[0]->coversAt(new \DateTimeImmutable('2027-01-15 12:00:00')));
        self::assertFalse($segments[0]->coversAt(new \DateTimeImmutable('2027-02-01 00:00:00')));
        self::assertFalse($segments[0]->coversAt(new \DateTimeImmutable('2026-12-31 23:59:59')));
    }

    public function testAnOpenEndedRuleGivesASegmentWithNoEnd(): void
    {
        $segments = (new PriceSegmentBuilder())->build(
            [$this->effect(1, EffectType::Percentage, 20.0, '2027-01-01 00:00:00', null)],
            100.0,
            new FlatRateTaxCalculator(),
        );

        self::assertCount(1, $segments);
        self::assertSame('2027-01-01 00:00:00', $segments[0]->validFrom?->format('Y-m-d H:i:s'));
        self::assertNull($segments[0]->validUntil);
    }

    /**
     * A permanent 10% and a January 20% with the smaller priority and the stop flag:
     * three periods, the January one priced by the fixed rule alone, the other two by
     * the permanent one.
     */
    public function testNestedWindowsCutTimeIntoPeriodsPricedByTheRulesRunningThere(): void
    {
        $segments = (new PriceSegmentBuilder())->build(
            [
                $this->effect(1, EffectType::Percentage, 10.0, priority: 100),
                $this->effect(2, EffectType::Percentage, 20.0, '2027-01-01 00:00:00', '2027-02-01 00:00:00', priority: 10, stop: true),
            ],
            100.0,
            new FlatRateTaxCalculator(),
        );

        self::assertCount(3, $segments);
        [$before, $january, $after] = $segments;

        self::assertNull($before->validFrom);
        self::assertSame('2027-01-01 00:00:00', $before->validUntil?->format('Y-m-d H:i:s'));
        self::assertEqualsWithDelta(90.0, $before->result->untaxedPrice, 0.000001);
        self::assertSame(1, $before->result->ruleId);

        self::assertEqualsWithDelta(80.0, $january->result->untaxedPrice, 0.000001);
        self::assertSame(2, $january->result->ruleId);
        self::assertSame([2], $january->result->appliedRuleIds);

        self::assertSame('2027-02-01 00:00:00', $after->validFrom?->format('Y-m-d H:i:s'));
        self::assertNull($after->validUntil);
        self::assertEqualsWithDelta(90.0, $after->result->untaxedPrice, 0.000001);
    }

    public function testAdjacentPeriodsWithTheSamePriceAreMerged(): void
    {
        // A dated rule that changes nothing to the price the permanent one gives:
        // one hundred percent of nothing, so the three periods collapse into one.
        $segments = (new PriceSegmentBuilder())->build(
            [
                $this->effect(1, EffectType::Percentage, 10.0, priority: 10, stop: true),
                $this->effect(2, EffectType::Percentage, 50.0, '2027-01-01 00:00:00', '2027-02-01 00:00:00', priority: 100),
            ],
            100.0,
            new FlatRateTaxCalculator(),
        );

        self::assertCount(1, $segments);
        self::assertNull($segments[0]->validFrom);
        self::assertNull($segments[0]->validUntil);
    }

    public function testPeriodsAlreadyOverAreDroppedWhenTheClockIsGiven(): void
    {
        $segments = (new PriceSegmentBuilder())->build(
            [
                $this->effect(1, EffectType::Percentage, 20.0, '2026-01-01 00:00:00', '2026-02-01 00:00:00'),
                $this->effect(2, EffectType::Percentage, 30.0, '2027-01-01 00:00:00', '2027-02-01 00:00:00'),
            ],
            100.0,
            new FlatRateTaxCalculator(),
            new \DateTimeImmutable('2026-06-01 00:00:00'),
        );

        self::assertCount(1, $segments);
        self::assertSame(2, $segments[0]->result->ruleId);
    }

    public function testTwoRulesEndingAtTheSameInstantMakeOneBoundary(): void
    {
        $segments = (new PriceSegmentBuilder())->build(
            [
                $this->effect(1, EffectType::Percentage, 10.0, null, '2027-02-01 00:00:00'),
                $this->effect(2, EffectType::Percentage, 10.0, null, '2027-02-01 00:00:00'),
            ],
            100.0,
            new FlatRateTaxCalculator(),
        );

        self::assertCount(1, $segments);
        self::assertContainsOnlyInstancesOf(PriceSegment::class, $segments);
        // 120 TTC, 10% then 10% -> 97.2 TTC -> 81 HT
        self::assertEqualsWithDelta(81.0, $segments[0]->result->untaxedPrice, 0.000001);
    }

    private function effect(
        int $id,
        EffectType $type,
        float $value,
        ?string $start = null,
        ?string $end = null,
        int $priority = 100,
        bool $stop = false,
    ): RuleEffect {
        return new RuleEffect(
            $id,
            $priority,
            $stop,
            $type,
            $value,
            true,
            null === $start ? null : new \DateTimeImmutable($start),
            null === $end ? null : new \DateTimeImmutable($end),
        );
    }
}
