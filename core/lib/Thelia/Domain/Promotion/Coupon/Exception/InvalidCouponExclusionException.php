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

namespace Thelia\Domain\Promotion\Coupon\Exception;

final class InvalidCouponExclusionException extends \InvalidArgumentException
{
    public static function selfExclusion(int $couponId): self
    {
        return new self(\sprintf('The promotion %d cannot exclude itself.', $couponId));
    }

    /**
     * @param list<int> $couponIds
     */
    public static function unknownCoupons(array $couponIds): self
    {
        return new self(\sprintf('The promotions %s do not exist, so they cannot be excluded.', implode(', ', $couponIds)));
    }

    public static function unknownCoupon(int $couponId): self
    {
        return new self(\sprintf('The promotion %d does not exist.', $couponId));
    }

    public static function concurrentEdit(?\Throwable $previous = null): self
    {
        return new self('Another change of the promotions got in the way, nothing was saved: try again.', 0, $previous);
    }
}
