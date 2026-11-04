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

namespace Thelia\Domain\Promotion\Coupon\Service;

use Thelia\Condition\Exception\UnmatchableConditionException;
use Thelia\Domain\Promotion\Coupon\Selection\DiscardedPromotion;
use Thelia\Domain\Promotion\Coupon\Selection\PromotionDiscardReason;
use Thelia\Domain\Promotion\Coupon\Selection\PromotionSelection;
use Thelia\Domain\Promotion\Coupon\Type\CouponAbstract;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;

/**
 * Chooses which promotions apply to a cart, whatever the order the buyer typed the codes in.
 *
 * Candidates are walked by priority, then (same priority) the automatic promotions before the
 * ones typed as a code, then by id of the promotion. For each one:
 * an expired promotion is ignored; once the chain is stopped it is discarded without being
 * evaluated; one whose conditions are not met (or cannot be evaluated) is ignored; one that never
 * applies with a kept promotion, or that applies alone while another is kept, is discarded;
 * otherwise it is kept, and when it asks to stop the chain (or applies alone) every following
 * promotion is discarded.
 *
 * A shop that sets no priority, no stop flag and no exclusion keeps the result it had before they
 * existed: when an automatic promotion applying alone matches, the last one by id wins, unless
 * cumulative automatic promotions come after it, which then replace it, and the codes are discarded.
 *
 * Pure: the pairs of promotions that exclude each other are given by the caller.
 */
final readonly class PromotionSelector
{
    /**
     * @param array<int, CouponInterface|null> $candidates
     * @param array<int, list<int>>            $exclusionsByCouponId promotion id => ids of the promotions it never applies with
     */
    public function select(array $candidates, array $exclusionsByCouponId = []): PromotionSelection
    {
        /** @var list<CouponInterface> $kept */
        $kept = [];
        /** @var list<DiscardedPromotion> $discarded */
        $discarded = [];
        $stoppedBy = null;
        $sorted = $this->sort($candidates);
        $legacyWinners = $this->legacyWinners($sorted, $exclusionsByCouponId);

        foreach ($sorted as $coupon) {
            if ($coupon->isExpired()) {
                continue;
            }

            if ($stoppedBy instanceof CouponInterface) {
                $discarded[] = new DiscardedPromotion($coupon, PromotionDiscardReason::Stopped, $stoppedBy);

                continue;
            }

            if (!$this->matches($coupon)) {
                continue;
            }

            $winner = $legacyWinners[spl_object_id($coupon)] ?? $this->findExcludingPromotion($coupon, $kept, $exclusionsByCouponId);

            if (null === $winner && !$coupon->isCumulative() && [] !== $kept) {
                $winner = $kept[0];
            }

            if ($winner instanceof CouponInterface) {
                $discarded[] = new DiscardedPromotion($coupon, PromotionDiscardReason::Excluded, $winner);

                continue;
            }

            $kept[] = $coupon;

            if ($coupon->isStopProcessing() || !$coupon->isCumulative()) {
                $stoppedBy = $coupon;
            }
        }

        return new PromotionSelection($kept, $discarded);
    }

    /**
     * @param array<int, CouponInterface|null> $candidates
     *
     * @return list<CouponInterface>
     */
    private function sort(array $candidates): array
    {
        $entries = [];

        foreach (array_values($candidates) as $position => $coupon) {
            if ($coupon instanceof CouponInterface) {
                $entries[] = [$coupon, $position];
            }
        }

        usort(
            $entries,
            fn (array $left, array $right): int => [$left[0]->getPriority(), $this->triggerRank($left[0]), $this->identifier($left[0]), $left[1]]
                <=> [$right[0]->getPriority(), $this->triggerRank($right[0]), $this->identifier($right[0]), $right[1]],
        );

        return array_map(static fn (array $entry): CouponInterface => $entry[0], $entries);
    }

    /**
     * The promotions the algorithm before the priorities set aside, each with the one that wins over
     * it, for a shop that uses neither priorities, stop flags nor exclusions. It walked the codes, then
     * the automatic promotions by id: a non cumulative one replaced the ones kept, a cumulative one was
     * added after cumulative ones and replaced a non cumulative one. Once an automatic promotion
     * applying alone matches, the codes were replaced, so only the automatic ones decide.
     *
     * @param list<CouponInterface> $sorted
     * @param array<int, list<int>> $exclusionsByCouponId
     *
     * @return array<int, CouponInterface> object id of the promotion set aside => the one that wins
     */
    private function legacyWinners(array $sorted, array $exclusionsByCouponId): array
    {
        $automatic = [];

        foreach ($sorted as $coupon) {
            if (0 !== $coupon->getPriority() || $coupon->isStopProcessing() || $this->hasExclusion($coupon, $exclusionsByCouponId)) {
                return [];
            }

            if ($this->isAutomatic($coupon) && !$coupon->isExpired()) {
                $automatic[] = $coupon;
            }
        }

        // Without an automatic promotion applying alone, the walk of select() decides alone.
        if ([] === array_filter($automatic, static fn (CouponInterface $coupon): bool => !$coupon->isCumulative())) {
            return [];
        }

        $kept = [];
        $resetByAutomatic = false;

        foreach (array_filter($automatic, $this->matches(...)) as $coupon) {
            if (!$coupon->isCumulative()) {
                $kept = [$coupon];
                $resetByAutomatic = true;
            } else {
                $kept = [] !== $kept && $kept[0]->isCumulative() ? [...$kept, $coupon] : [$coupon];
            }
        }

        if (!$resetByAutomatic) {
            return [];
        }

        $winners = [];

        foreach ($sorted as $coupon) {
            if (!\in_array($coupon, $kept, true)) {
                $winners[spl_object_id($coupon)] = $kept[0];
            }
        }

        return $winners;
    }

    /** @param array<int, list<int>> $exclusionsByCouponId */
    private function hasExclusion(CouponInterface $coupon, array $exclusionsByCouponId): bool
    {
        $couponId = $this->modelId($coupon);

        if (null === $couponId) {
            return false;
        }

        if ([] !== ($exclusionsByCouponId[$couponId] ?? [])) {
            return true;
        }

        foreach ($exclusionsByCouponId as $excludedIds) {
            if (\in_array($couponId, $excludedIds, true)) {
                return true;
            }
        }

        return false;
    }

    private function matches(CouponInterface $coupon): bool
    {
        try {
            return $coupon->isMatching();
        } catch (UnmatchableConditionException) {
            return false;
        }
    }

    /**
     * @param list<CouponInterface> $kept
     * @param array<int, list<int>> $exclusionsByCouponId
     */
    private function findExcludingPromotion(CouponInterface $coupon, array $kept, array $exclusionsByCouponId): ?CouponInterface
    {
        $couponId = $this->modelId($coupon);

        if (null === $couponId) {
            return null;
        }

        foreach ($kept as $keptCoupon) {
            $keptId = $this->modelId($keptCoupon);

            if (null === $keptId) {
                continue;
            }

            if (\in_array($keptId, $exclusionsByCouponId[$couponId] ?? [], true)
                || \in_array($couponId, $exclusionsByCouponId[$keptId] ?? [], true)) {
                return $keptCoupon;
            }
        }

        return null;
    }

    private function modelId(CouponInterface $coupon): ?int
    {
        return $coupon instanceof CouponAbstract ? $coupon->getCouponModelId() : null;
    }

    /**
     * At the same priority an automatic promotion ranks before a promotion typed as a code: with
     * every priority left at 0, "one code and some automatic promotions" keeps the discount the
     * shop had before the priorities existed.
     */
    private function triggerRank(CouponInterface $coupon): int
    {
        return $this->isAutomatic($coupon) ? 0 : 1;
    }

    private function isAutomatic(CouponInterface $coupon): bool
    {
        return $coupon instanceof CouponAbstract && $coupon->isAutomatic();
    }

    /** A promotion that does not come from the database ranks after the stored ones of its priority. */
    private function identifier(CouponInterface $coupon): int
    {
        return $this->modelId($coupon) ?? \PHP_INT_MAX;
    }
}
