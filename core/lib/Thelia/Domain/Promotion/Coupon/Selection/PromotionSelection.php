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

namespace Thelia\Domain\Promotion\Coupon\Selection;

use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;

final readonly class PromotionSelection
{
    /**
     * @param list<CouponInterface>    $kept      the promotions that apply, in the order they are evaluated
     * @param list<DiscardedPromotion> $discarded the promotions another one pushed aside
     */
    public function __construct(
        public array $kept = [],
        public array $discarded = [],
    ) {
    }
}
