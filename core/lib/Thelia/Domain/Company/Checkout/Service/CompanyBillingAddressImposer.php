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

namespace Thelia\Domain\Company\Checkout\Service;

use Thelia\Domain\Addressing\Service\AddressAccess;
use Thelia\Domain\Cart\Service\CartAddressService;
use Thelia\Model\Cart;

/**
 * Invoices the order of a buyer to the billing address of their company, whatever the cart
 * held: the choice of the checkout already replaces the buyer's, but a cart prepared before the
 * buyer joined the company, or before the company chose or changed its billing address, still
 * carries another one when the order is placed.
 *
 * The address of the cart is written again from the address of the company, so that what the
 * company changed on it since is what the order freezes.
 */
final readonly class CompanyBillingAddressImposer
{
    public function __construct(
        private AddressAccess $addressAccess,
        private CartAddressService $cartAddressService,
    ) {
    }

    public function imposeOn(Cart $cart, int $customerId): void
    {
        $address = $this->addressAccess->billingAddressImposedOn($customerId);

        if (null === $address) {
            return;
        }

        $current = $cart->getCartAddressRelatedByAddressInvoiceId();

        // An address shared with the delivery is not rewritten: the delivery would follow.
        if (null !== $current && $current->getId() === $cart->getAddressDeliveryId()) {
            $current = null;
        }

        $cart
            ->setAddressInvoiceId($this->cartAddressService->getOrCreateCartAddressFromAddress($address, $current)->getId())
            ->save();
    }
}
