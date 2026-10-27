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

use Thelia\Model\Cart;
use Thelia\Model\Customer;

/**
 * The cart and the buyer the promotions are evaluated for, while the discount of a cart is
 * priced outside of any session (the front API is stateless).
 *
 * The coupon facade reads the cart, the customer and the currency off the session. For the
 * time of the call, it reads them here instead, and the promotions that go through it see the
 * cart they are asked about. Static, like {@see \Thelia\Domain\Shipping\ProductRule\ShippingCartScope},
 * because the facade is shared by every coupon and condition and is handed no argument.
 */
final class CartDiscountScope
{
    private static ?Cart $cart = null;
    private static ?Customer $buyer = null;

    /**
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     */
    public static function within(Cart $cart, ?Customer $buyer, callable $call): mixed
    {
        $previousCart = self::$cart;
        $previousBuyer = self::$buyer;
        self::$cart = $cart;
        self::$buyer = $buyer;

        try {
            return $call();
        } finally {
            self::$cart = $previousCart;
            self::$buyer = $previousBuyer;
        }
    }

    public static function cart(): ?Cart
    {
        return self::$cart;
    }

    public static function buyer(): ?Customer
    {
        return self::$buyer;
    }
}
