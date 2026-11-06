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

namespace Thelia\Domain\Pricing;

/**
 * An amount rounded half up to the cent, the same on every PHP version.
 *
 * A line sold by measure is a product of floats: 0.7 kg at 0.75 is 0.52499999…, not 0.525.
 * Up to PHP 8.3 round() pre-rounded it back to 0.525 and charged 0.53; PHP 8.4 dropped that
 * pre-rounding and charges 0.52. The amount is brought back to the six decimals a price is
 * stored with before it is rounded, which is what the order total does in SQL
 * (CAST(… AS DECIMAL(20,6)) then ROUND(…, 2)): the cart, the order, the refund and the
 * documents then agree on the cent, whatever PHP the shop runs.
 */
final class MoneyRounding
{
    private const STORED_DECIMALS = 6;

    public static function toTheCent(float $amount): float
    {
        return round(round($amount, self::STORED_DECIMALS), 2);
    }
}
