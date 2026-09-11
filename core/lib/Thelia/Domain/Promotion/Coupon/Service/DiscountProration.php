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

/**
 * Distributes a stored cart discount over the amounts the individual promotions
 * priced. The stored `cart.discount` is the authoritative figure — it was capped
 * and rounded when it was written — so the per-promotion amounts shown to the
 * customer are scaled onto it, and the rounding remainder lands on the last
 * line: the listed amounts always add up to the stored discount, to the cent.
 */
final class DiscountProration
{
    private function __construct()
    {
    }

    /**
     * @param list<float> $rawAmounts what each promotion priced, in order
     * @param float       $target     the stored discount the amounts must add up to
     *
     * @return list<float> one prorated amount per raw amount, summing exactly to
     *                     round($target, 2); empty when there is nothing to
     *                     distribute or nothing to distribute over
     */
    public static function prorate(array $rawAmounts, float $target): array
    {
        $target = round($target, 2);

        if ($target <= 0.0 || [] === $rawAmounts) {
            return [];
        }

        $rawSum = array_sum($rawAmounts);

        if ($rawSum <= 0.0) {
            return [];
        }

        $factor = $target / $rawSum;
        $lastIndex = array_key_last($rawAmounts);

        $prorated = [];
        $allocated = 0.0;

        foreach ($rawAmounts as $index => $rawAmount) {
            if ($index === $lastIndex) {
                // The rounding remainder of every other line lands here, which is
                // what makes the sum exact.
                $prorated[] = round($target - $allocated, 2);

                break;
            }

            $amount = round($rawAmount * $factor, 2);
            $allocated = round($allocated + $amount, 2);
            $prorated[] = $amount;
        }

        return $prorated;
    }

    /**
     * The scale between what the promotions priced and what the cart stores,
     * for a value that follows the same proration without belonging to the
     * summed list — the share of the discount one offered line carries.
     */
    public static function factor(array $rawAmounts, float $target): float
    {
        $target = round($target, 2);
        $rawSum = array_sum($rawAmounts);

        if ($target <= 0.0 || $rawSum <= 0.0) {
            return 0.0;
        }

        return $target / $rawSum;
    }
}
