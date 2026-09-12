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

namespace Thelia\Domain\Promotion\Coupon\OfferedLine;

/**
 * What a promotion asks the cart to hold as an offered line: a quantity of a
 * product, marked as offered by the coupon row identified by couponId.
 */
final readonly class OfferedLineRequest
{
    public function __construct(
        public int $productId,
        public int $quantity,
        public int $couponId,
    ) {
    }
}
