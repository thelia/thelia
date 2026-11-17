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

namespace Thelia\Domain\Report\Customer;

/**
 * A segment of customers: how many they are, what they spent in the period and its share of the
 * revenue of the period, and what they spent since their first order and its share of what every
 * customer spent since.
 */
final readonly class SegmentRow
{
    public function __construct(
        public CustomerSegment $segment,
        public int $customerCount,
        public float $periodRevenue,
        public float $periodShare,
        public float $lifetimeRevenue,
        public float $lifetimeShare,
    ) {
    }
}
