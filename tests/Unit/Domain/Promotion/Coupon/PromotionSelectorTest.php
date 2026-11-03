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

namespace Thelia\Tests\Unit\Domain\Promotion\Coupon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Condition\Exception\UnmatchableConditionException;
use Thelia\Domain\Promotion\Coupon\Selection\PromotionDiscardReason;
use Thelia\Domain\Promotion\Coupon\Service\PromotionSelector;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Domain\Promotion\Coupon\Type\RemoveXAmount;

final class PromotionSelectorTest extends TestCase
{
    private PromotionSelector $selector;

    protected function setUp(): void
    {
        $this->selector = new PromotionSelector();
    }

    public function testTwoCumulativePromotionsAreKeptInPriorityOrder(): void
    {
        $tenth = $this->promotion(1, priority: 10);
        $twentieth = $this->promotion(2, priority: 20);

        $selection = $this->selector->select([$twentieth, $tenth]);

        self::assertSame([$tenth, $twentieth], $selection->kept);
        self::assertSame([], $selection->discarded);
    }

    public function testAStoppingPromotionDiscardsTheNextOnesWhateverTheEnteredOrder(): void
    {
        $stopping = $this->promotion(1, priority: 10, stop: true);
        $next = $this->promotion(2, priority: 20);

        foreach ([[$stopping, $next], [$next, $stopping]] as $candidates) {
            $selection = $this->selector->select($candidates);

            self::assertSame([$stopping], $selection->kept);
            self::assertCount(1, $selection->discarded);
            self::assertSame($next, $selection->discarded[0]->coupon);
            self::assertSame(PromotionDiscardReason::Stopped, $selection->discarded[0]->reason);
            self::assertSame($stopping, $selection->discarded[0]->winner);
        }
    }

    public function testAnExcludedPromotionIsDiscardedAndNamesTheOneThatWon(): void
    {
        $first = $this->promotion(1, priority: 10);
        $second = $this->promotion(2, priority: 20);

        $selection = $this->selector->select([$second, $first], [1 => [2], 2 => [1]]);

        self::assertSame([$first], $selection->kept);
        self::assertSame($second, $selection->discarded[0]->coupon);
        self::assertSame(PromotionDiscardReason::Excluded, $selection->discarded[0]->reason);
        self::assertSame($first, $selection->discarded[0]->winner);
    }

    public function testAPairGivenInOneDirectionOnlyStillExcludes(): void
    {
        $first = $this->promotion(1, priority: 10);
        $second = $this->promotion(2, priority: 20);

        $selection = $this->selector->select([$first, $second], [1 => [2]]);

        self::assertSame([$first], $selection->kept);
        self::assertSame(PromotionDiscardReason::Excluded, $selection->discarded[0]->reason);
    }

    public function testTheChainStopsAfterTheSecondOfThreePromotionsWithoutEvaluatingTheThird(): void
    {
        $first = $this->promotion(1, priority: 1);
        $second = $this->promotion(2, priority: 2, stop: true);
        $third = $this->promotion(3, priority: 3, matching: 'never');

        $selection = $this->selector->select([$third, $second, $first]);

        self::assertSame([$first, $second], $selection->kept);
        self::assertSame($third, $selection->discarded[0]->coupon);
        self::assertSame(PromotionDiscardReason::Stopped, $selection->discarded[0]->reason);
        self::assertSame($second, $selection->discarded[0]->winner);
    }

    public function testAPromotionThatAppliesAloneIsKeptAloneInTheCart(): void
    {
        $alone = $this->promotion(1, cumulative: false);

        $selection = $this->selector->select([$alone]);

        self::assertSame([$alone], $selection->kept);
        self::assertSame([], $selection->discarded);
    }

    public function testAPromotionThatAppliesAloneStopsTheFollowingOnes(): void
    {
        $alone = $this->promotion(1, priority: 1, cumulative: false);
        $other = $this->promotion(2, priority: 2, matching: 'never');

        $selection = $this->selector->select([$other, $alone]);

        self::assertSame([$alone], $selection->kept);
        self::assertSame(PromotionDiscardReason::Stopped, $selection->discarded[0]->reason);
    }

    public function testAPromotionThatAppliesAloneIsDiscardedWhenAnotherIsAlreadyKept(): void
    {
        $first = $this->promotion(1, priority: 1);
        $alone = $this->promotion(2, priority: 2, cumulative: false);

        $selection = $this->selector->select([$alone, $first]);

        self::assertSame([$first], $selection->kept);
        self::assertSame($alone, $selection->discarded[0]->coupon);
        self::assertSame(PromotionDiscardReason::Excluded, $selection->discarded[0]->reason);
        self::assertSame($first, $selection->discarded[0]->winner);
    }

    public function testTwoPromotionsApplyingAloneWithTheSamePriorityLetTheSmallestIdWin(): void
    {
        $smallest = $this->promotion(4, cumulative: false);
        $largest = $this->promotion(9, cumulative: false);

        foreach ([[$smallest, $largest], [$largest, $smallest]] as $candidates) {
            $selection = $this->selector->select($candidates);

            self::assertSame([$smallest], $selection->kept);
            self::assertSame($largest, $selection->discarded[0]->coupon);
            self::assertSame(PromotionDiscardReason::Stopped, $selection->discarded[0]->reason);
            self::assertSame($smallest, $selection->discarded[0]->winner);
        }
    }

    public function testAnUnevaluableConditionIsIgnoredAndTheChainContinues(): void
    {
        $first = $this->promotion(1, priority: 1);
        $unevaluable = $this->promotion(2, priority: 2, matching: 'unmatchable');
        $third = $this->promotion(3, priority: 3);

        $selection = $this->selector->select([$first, $unevaluable, $third]);

        self::assertSame([$first, $third], $selection->kept);
        self::assertSame([], $selection->discarded);
    }

    public function testAPromotionThatDoesNotMatchIsIgnoredNotDiscarded(): void
    {
        $matching = $this->promotion(1, priority: 1);
        $notMatching = $this->promotion(2, priority: 2, matching: false);

        $selection = $this->selector->select([$notMatching, $matching]);

        self::assertSame([$matching], $selection->kept);
        self::assertSame([], $selection->discarded);
    }

    public function testAnExpiredPromotionIsIgnored(): void
    {
        $expired = $this->promotion(1, priority: 1, expired: true, matching: 'never');
        $other = $this->promotion(2, priority: 2);

        $selection = $this->selector->select([$expired, $other]);

        self::assertSame([$other], $selection->kept);
        self::assertSame([], $selection->discarded);
    }

    public function testAnEmptyListGivesAnEmptySelection(): void
    {
        $selection = $this->selector->select([]);

        self::assertSame([], $selection->kept);
        self::assertSame([], $selection->discarded);
    }

    public function testTheOrderIsStableAcrossTwoRunsAndIgnoresTheEnteredOrder(): void
    {
        $same = [
            $this->promotion(7),
            $this->promotion(3),
            $this->promotion(5, priority: -1),
            $this->promotion(1, priority: 2),
        ];

        $first = $this->selector->select($same);
        $second = $this->selector->select(array_reverse($same));

        self::assertSame([$same[2], $same[1], $same[0], $same[3]], $first->kept);
        self::assertSame($first->kept, $second->kept);
        self::assertSame($first->kept, $this->selector->select($same)->kept);
    }

    public function testAnExcludedPromotionWhoseConditionsAreNotMetIsIgnoredNotAnnounced(): void
    {
        $winner = $this->promotion(1, priority: 1);
        $excludedButNotMatching = $this->promotion(2, priority: 2, matching: false);

        $selection = $this->selector->select([$winner, $excludedButNotMatching], [1 => [2], 2 => [1]]);

        self::assertSame([$winner], $selection->kept);
        self::assertSame([], $selection->discarded);
    }

    public function testAPromotionThatAppliesAloneWhoseConditionsAreNotMetIsIgnoredNotAnnounced(): void
    {
        $first = $this->promotion(1, priority: 1);
        $aloneButNotMatching = $this->promotion(2, priority: 2, cumulative: false, matching: false);

        $selection = $this->selector->select([$first, $aloneButNotMatching]);

        self::assertSame([$first], $selection->kept);
        self::assertSame([], $selection->discarded);
    }

    public function testAnUnevaluablePromotionThatWouldBeExcludedIsIgnoredNotAnnounced(): void
    {
        $winner = $this->promotion(1, priority: 1);
        $unevaluable = $this->promotion(2, priority: 2, matching: 'unmatchable');

        $selection = $this->selector->select([$winner, $unevaluable], [1 => [2]]);

        self::assertSame([$winner], $selection->kept);
        self::assertSame([], $selection->discarded);
    }

    public function testAStoppedPromotionIsAnnouncedWithoutEvaluatingItsConditions(): void
    {
        $stopping = $this->promotion(1, priority: 1, stop: true);
        $stopped = $this->promotion(2, priority: 2, matching: 'never');

        $selection = $this->selector->select([$stopped, $stopping]);

        self::assertSame([$stopping], $selection->kept);
        self::assertSame(PromotionDiscardReason::Stopped, $selection->discarded[0]->reason);
        self::assertSame($stopped, $selection->discarded[0]->coupon);
    }

    public function testAtTheSamePriorityAnAutomaticPromotionRanksBeforeACodeWhateverTheIds(): void
    {
        foreach ([[1, 9], [9, 1]] as [$codeId, $automaticId]) {
            $code = $this->promotion($codeId);
            $automatic = $this->promotion($automaticId, automatic: true);

            foreach ([[$code, $automatic], [$automatic, $code]] as $candidates) {
                self::assertSame([$automatic, $code], $this->selector->select($candidates)->kept);
            }
        }
    }

    public function testThePriorityStillBeatsTheTriggerMode(): void
    {
        $code = $this->promotion(9, priority: -1);
        $automatic = $this->promotion(1, automatic: true);

        self::assertSame([$code, $automatic], $this->selector->select([$automatic, $code])->kept);
    }

    public function testAPromotionWithoutModelIdRanksAfterTheStoredOnesOfItsPriority(): void
    {
        $stored = $this->promotion(5);
        $unsaved = $this->promotion(null);

        foreach ([[$stored, $unsaved], [$unsaved, $stored]] as $candidates) {
            self::assertSame([$stored, $unsaved], $this->selector->select($candidates)->kept);
        }
    }

    /**
     * Every priority left at 0 (a shop that never heard of priorities): one code and the automatic
     * promotions give the discount the previous algorithm gave. The oracle is the previous
     * CouponManager::sortCoupons, copied: the matching promotions in the order they were fed (the codes
     * typed, then the automatic ones by id), a non cumulative one resets the list, a cumulative one
     * resets it too unless the first kept one is cumulative.
     *
     * @return iterable<string, array{list<array{string, bool, bool}>, array<int, string>}>
     */
    public static function legacyEquivalenceCases(): iterable
    {
        // [code cumulative or null for no code, automatic promotions cumulative flags]
        $shapes = [];

        foreach ([null, true, false] as $code) {
            foreach ([[], [true], [false], [true, true]] as $automatics) {
                if (null === $code && [] === $automatics) {
                    continue;
                }

                $shapes[] = [$code, $automatics];
            }
        }

        foreach ($shapes as [$code, $automatics]) {
            foreach (['code before automatics' => 1, 'code after automatics' => 9] as $order => $codeId) {
                $label = \sprintf(
                    'code %s + automatics [%s], %s',
                    null === $code ? 'none' : ($code ? 'cumulative' : 'alone'),
                    implode(',', array_map(static fn (bool $cumulative): string => $cumulative ? 'c' : 'nc', $automatics)),
                    $order,
                );

                $promotions = [];

                if (null !== $code) {
                    $promotions[] = ['CODE', $code, false, $codeId];
                }

                foreach ($automatics as $index => $cumulative) {
                    $promotions[] = ['AUTO'.($index + 1), $cumulative, true, 4 + $index];
                }

                yield $label => [$promotions];
            }
        }
    }

    /**
     * @param list<array{string, bool, bool, int}> $promotions [code, cumulative, automatic, id], codes first as fed to the old algorithm
     */
    #[DataProvider('legacyEquivalenceCases')]
    public function testWithAllPrioritiesAtZeroOneCodeAndAutomaticPromotionsKeepTheLegacyResult(array $promotions): void
    {
        $candidates = [];

        foreach ($promotions as [$code, $cumulative, $automatic, $id]) {
            $candidates[$code] = $this->promotion($id, cumulative: $cumulative, automatic: $automatic, label: $code);
        }

        $legacy = $this->legacySort(array_values($candidates));

        foreach ([array_values($candidates), array_reverse(array_values($candidates))] as $fed) {
            $kept = array_map(static fn (CouponInterface $promotion): string => $promotion->getCode(), $this->selector->select($fed)->kept);
            sort($kept);

            self::assertSame($legacy, $kept);
        }
    }

    public function testTwoAutomaticPromotionsOneOfThemAloneNowKeepTheSmallestIdInsteadOfTheLast(): void
    {
        // The one case that stays different from the previous algorithm (the last non cumulative one won,
        // and the cumulative ones after it replaced it). Frozen here on purpose.
        $alone = $this->promotion(4, cumulative: false, automatic: true, label: 'AUTO_ALONE');
        $cumulative = $this->promotion(5, cumulative: true, automatic: true, label: 'AUTO_CUMULATIVE');

        self::assertSame(['AUTO_CUMULATIVE'], $this->legacySort([$alone, $cumulative]));
        self::assertSame($alone, $this->selector->select([$alone, $cumulative])->kept[0]);
        self::assertCount(1, $this->selector->select([$alone, $cumulative])->kept);

        // The other way round (the cumulative one has the smaller id): the last one used to win, the first wins now.
        $cumulative = $this->promotion(4, cumulative: true, automatic: true, label: 'AUTO_CUMULATIVE');
        $alone = $this->promotion(5, cumulative: false, automatic: true, label: 'AUTO_ALONE');

        self::assertSame(['AUTO_ALONE'], $this->legacySort([$cumulative, $alone]));
        self::assertSame([$cumulative], $this->selector->select([$cumulative, $alone])->kept);
    }

    /**
     * The algorithm CouponManager::sortCoupons had before the priorities, copied.
     *
     * @param list<CouponInterface> $coupons
     *
     * @return list<string> the codes kept, sorted
     */
    private function legacySort(array $coupons): array
    {
        $kept = [];

        foreach ($coupons as $coupon) {
            if (!$coupon->isMatching()) {
                continue;
            }

            if ($coupon->isCumulative()) {
                $kept = isset($kept[0]) && $kept[0]->isCumulative() ? [...$kept, $coupon] : [$coupon];
            } else {
                $kept = [$coupon];
            }
        }

        $codes = array_map(static fn (CouponInterface $coupon): string => $coupon->getCode(), $kept);
        sort($codes);

        return $codes;
    }

    /**
     * @param bool|'never'|'unmatchable' $matching
     */
    private function promotion(
        ?int $id,
        int $priority = 0,
        bool $cumulative = true,
        bool $stop = false,
        bool|string $matching = true,
        bool $expired = false,
        bool $automatic = false,
        string $label = '',
    ): CouponInterface {
        $promotion = $this->createPartialMock(
            RemoveXAmount::class,
            ['isMatching', 'isExpired', 'isCumulative', 'getPriority', 'isStopProcessing', 'getCouponModelId', 'isAutomatic', 'getCode'],
        );

        $promotion->method('getCouponModelId')->willReturn($id);
        $promotion->method('isAutomatic')->willReturn($automatic);
        $promotion->method('getCode')->willReturn($label);
        $promotion->method('getPriority')->willReturn($priority);
        $promotion->method('isCumulative')->willReturn($cumulative);
        $promotion->method('isStopProcessing')->willReturn($stop);
        $promotion->method('isExpired')->willReturn($expired);

        if ('never' === $matching) {
            $promotion->expects(self::never())->method('isMatching');
        } elseif ('unmatchable' === $matching) {
            $promotion->method('isMatching')->willThrowException((new \ReflectionClass(UnmatchableConditionException::class))->newInstanceWithoutConstructor());
        } else {
            $promotion->method('isMatching')->willReturn($matching);
        }

        return $promotion;
    }
}
