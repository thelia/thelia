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

namespace Thelia\Domain\Shipping\DestinationRestriction;

use Thelia\Model\Cart;

/**
 * The destinations a cart is known to go to. Read from the cart and its customer,
 * never from the session.
 */
final class CartDestinations
{
    /**
     * Where the parcels go: the delivery address of the cart, or, before one is
     * chosen and only when asked, the default address of the customer.
     */
    public static function delivery(Cart $cart, bool $customerDefaultWhenUnset = false): ?Destination
    {
        if (null !== $address = $cart->getCartAddressRelatedByAddressDeliveryId()) {
            return Destination::of($address);
        }

        return $customerDefaultWhenUnset ? self::customerDefault($cart) : null;
    }

    /**
     * Where a download is licensed to: the invoice address of the cart, or, before one
     * is chosen and only when asked, the default address of the customer.
     */
    public static function billing(Cart $cart, bool $customerDefaultWhenUnset = false): ?Destination
    {
        if (null !== $address = $cart->getCartAddressRelatedByAddressInvoiceId()) {
            return Destination::of($address);
        }

        return $customerDefaultWhenUnset ? self::customerDefault($cart) : null;
    }

    private static function customerDefault(Cart $cart): ?Destination
    {
        $address = $cart->getCustomer()?->getDefaultAddress();

        return null !== $address ? Destination::of($address) : null;
    }
}
