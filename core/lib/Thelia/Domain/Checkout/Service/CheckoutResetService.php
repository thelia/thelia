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

use Thelia\Domain\Cart\CartService;

readonly class CheckoutResetService
{
    public function __construct(private CartService $cartService)
    {
    }

    public function reset(): void
    {
        $cart = $this->cartService->getCart();

        $cart
            ->setDeliveryModuleId(null)
            ->setAddressDeliveryId(null)
            ->setAddressInvoiceId(null)
            ->setDeliveryModuleId(null)
            ->setPaymentModuleId(null)
            ->save();

        $this->cartService->clearCartPostage();
    }
}
