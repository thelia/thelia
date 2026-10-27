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

namespace Thelia\Domain\Checkout\Service;

use Propel\Runtime\Exception\PropelException;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;

/**
 * The one number a wallet's sheet shows and the shop accepts.
 *
 * The sheet asks for it before it opens, and the placement checks the wallet's
 * confirmation against it: both read it here, so the amount a buyer agrees to is the one
 * the order is placed at.
 */
final readonly class ExpressCheckoutTotal
{
    /**
     * The grand total of the order this cart becomes: the goods taxed for the address the
     * cart is taxed for, the postage of the chosen carrier (untaxed for a tax-exempt cart)
     * and the gift wrapping. Null until a carrier and that address are on the cart.
     *
     * @throws PropelException
     */
    public function of(Cart $cart): ?float
    {
        $taxedFor = $this->addressTheCartIsTaxedFor($cart);
        $country = $taxedFor?->getCountry();

        if (null === $cart->getDeliveryModuleId() || null === $country) {
            return null;
        }

        return round($cart->getTaxedAmount($country, true, $taxedFor->getState(), true, true), 2);
    }

    /**
     * The delivery address the checkout chose. A cart with nothing to ship is never asked
     * for one: it is taxed at the address the checkout gives it, the default address of its
     * buyer ({@see \Thelia\Domain\Shipping\Service\DeliverySetupService::setupVirtualDelivery()}),
     * so a wallet and any other payment method tax the same cart in the same place. A buyer
     * with no address of their own is taxed where they are invoiced.
     *
     * @throws PropelException
     */
    public function addressTheCartIsTaxedFor(Cart $cart): Address|CartAddress|null
    {
        $delivery = $cart->getCartAddressRelatedByAddressDeliveryId();

        if (null !== $delivery || !$cart->isVirtual()) {
            return $delivery;
        }

        return $this->addressOfTheBuyer($cart) ?? $cart->getCartAddressRelatedByAddressInvoiceId();
    }

    /**
     * @throws PropelException
     */
    private function addressOfTheBuyer(Cart $cart): ?Address
    {
        $customer = $cart->getCustomer();

        if (null === $customer) {
            return null;
        }

        return $customer->getDefaultAddress() ?? AddressQuery::create()->filterByCustomer($customer)->findOne();
    }
}
