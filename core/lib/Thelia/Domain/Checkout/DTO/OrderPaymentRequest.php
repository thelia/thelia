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

namespace Thelia\Domain\Checkout\DTO;

use Thelia\Model\Cart;
use Thelia\Model\Currency;
use Thelia\Model\Lang;

/**
 * What an order is raised with: the cart, the four choices it is written from, and the
 * currency and language it is frozen in.
 *
 * Five identifiers of the same shape used to travel side by side as positional arguments,
 * which is four chances to hand a delivery address where an invoice address was expected
 * and be told nothing — every one of them is an int, and the compiler has no opinion.
 *
 * The currency and the language stay optional: the tunnel of a theme leaves them out and
 * the listener reads them off the session, as it always has. A caller with no session
 * states them, and they are what the order is frozen with.
 */
final readonly class OrderPaymentRequest
{
    public function __construct(
        public Cart $cart,
        public int $deliveryAddressId,
        public int $invoiceAddressId,
        public int $deliveryModuleId,
        public int $paymentModuleId,
        public ?Currency $currency = null,
        public ?Lang $lang = null,
    ) {
    }

    /**
     * The placement of a caller with no session: every choice is read off the cart, where
     * the selection operations wrote it, and nothing is restated by the caller.
     */
    public static function ofTheChoicesOnTheCart(CheckoutPlacementRequest $placement): self
    {
        $cart = $placement->cart;

        return new self(
            $cart,
            (int) $cart->getAddressDeliveryId(),
            (int) $cart->getAddressInvoiceId(),
            (int) $cart->getDeliveryModuleId(),
            (int) $cart->getPaymentModuleId(),
            $placement->currency,
            $placement->lang,
        );
    }
}
