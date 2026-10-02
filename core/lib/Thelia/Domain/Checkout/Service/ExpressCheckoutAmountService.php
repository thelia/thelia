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

use Symfony\Component\HttpFoundation\Request;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutConfirmationDeniedException;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutRefusedException;

/**
 * What a wallet's sheet charges for the cart the checkout prepared.
 *
 * Asked by the module's script every time the checkout changes the carrier or the cart,
 * before the sheet opens: the sheet shows one final number, and it has to be the one the
 * shop will accept.
 */
final readonly class ExpressCheckoutAmountService
{
    public function __construct(
        private ExpressCheckoutGate $gate,
    ) {
    }

    /**
     * The goods taxed for the delivery address the checkout chose, plus the postage of the
     * chosen carrier. Null until a carrier and a delivery address are on the cart, which is
     * when the sheet may not open yet.
     *
     * The same sum the placement checks the wallet's total against, so the number a buyer
     * agrees to is the one the shop accepts.
     *
     * @throws ExpressCheckoutConfirmationDeniedException when the request is not one the shop reads at all
     * @throws ExpressCheckoutRefusedException            when there is nothing to pay for
     */
    public function amountOfTheCheckout(Request $request, string $moduleCode, string $token): ?float
    {
        $cart = $this->gate->open($request, $moduleCode, $token)->cart;
        $country = $cart->getCartAddressRelatedByAddressDeliveryId()?->getCountry();

        if (null === $cart->getDeliveryModuleId() || null === $country) {
            return null;
        }

        return round((float) $cart->getTaxedAmount($country) + (float) $cart->getPostage(), 2);
    }
}
