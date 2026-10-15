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

namespace Thelia\Domain\Shipping\ProductRule;

use Thelia\Model\Cart;

/**
 * The cart a carrier is asked to price, while it prices it.
 *
 * Delivery modules read the cart of the session rather than the one the postage event
 * carries. To have them price only what they will carry, the cart without its offered
 * lines stands in for the session cart during the quote; Session::getSessionCart()
 * answers it first. Static because the session is not a service the scope could be
 * handed to.
 */
final class ShippingCartScope
{
    private static ?Cart $cart = null;

    /**
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     */
    public static function within(Cart $cart, callable $call): mixed
    {
        $previousCart = self::$cart;
        self::$cart = $cart;

        try {
            return $call();
        } finally {
            self::$cart = $previousCart;
        }
    }

    public static function cart(): ?Cart
    {
        return self::$cart;
    }
}
