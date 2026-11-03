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

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Base\CouponExclusionQuery as BaseCouponExclusionQuery;
use Thelia\Model\Map\CouponExclusionTableMap;

/**
 * Skeleton subclass for performing query and update operations on the 'coupon_exclusion' table.
 *
 * A pair of promotions that never apply together is stored in both directions.
 */
class CouponExclusionQuery extends BaseCouponExclusionQuery
{
    /**
     * @return list<int> the promotions this one never applies with
     */
    public function excludedCouponIdsFor(int $couponId): array
    {
        $rows = $this
            ->clear()
            ->filterByCouponId($couponId)
            ->select([CouponExclusionTableMap::COL_EXCLUDED_COUPON_ID])
            ->orderByExcludedCouponId()
            ->find();

        return array_values(array_map(intval(...), $rows->toArray()));
    }

    /**
     * Only the pairs whose two sides are among the candidates.
     *
     * @param list<int> $candidateCouponIds
     *
     * @return array<int, list<int>> promotion id => the candidates it never applies with
     */
    public function excludedCouponIdsAmong(array $candidateCouponIds): array
    {
        if ([] === $candidateCouponIds) {
            return [];
        }

        $rows = $this
            ->clear()
            ->filterByCouponId($candidateCouponIds, Criteria::IN)
            ->filterByExcludedCouponId($candidateCouponIds, Criteria::IN)
            ->select([CouponExclusionTableMap::COL_COUPON_ID, CouponExclusionTableMap::COL_EXCLUDED_COUPON_ID])
            ->orderByCouponId()
            ->orderByExcludedCouponId()
            ->find();

        $excludedByCoupon = [];

        foreach ($rows->toArray() as $row) {
            $excludedByCoupon[(int) $row[CouponExclusionTableMap::COL_COUPON_ID]][] = (int) $row[CouponExclusionTableMap::COL_EXCLUDED_COUPON_ID];
        }

        return $excludedByCoupon;
    }

    /**
     * @param list<int> $couponIds
     *
     * @return array<int, int> promotion id => how many promotions it excludes (absent when none)
     */
    public function countByCoupons(array $couponIds): array
    {
        if ([] === $couponIds) {
            return [];
        }

        $rows = $this
            ->clear()
            ->filterByCouponId($couponIds, Criteria::IN)
            ->groupByCouponId()
            ->withColumn('COUNT(*)', 'exclusion_count')
            ->select([CouponExclusionTableMap::COL_COUPON_ID, 'exclusion_count'])
            ->find();

        $countByCoupon = [];

        foreach ($rows->toArray() as $row) {
            $countByCoupon[(int) $row[CouponExclusionTableMap::COL_COUPON_ID]] = (int) $row['exclusion_count'];
        }

        return $countByCoupon;
    }
}
