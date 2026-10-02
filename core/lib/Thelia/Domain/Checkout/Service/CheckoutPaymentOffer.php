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

use Thelia\Module\ExpressPaymentModuleInterface;

/**
 * Whether the checkout may place an order itself with a payment module.
 *
 * Every payment module is listed among the payment methods of the checkout, an express one
 * included: choosing it turns the order button into the wallet's own button. But the
 * checkout never places an order with a module that takes express payment only: only the
 * wallet's confirmation does, since an order placed without the sheet would be marked paid
 * with no money taken. A module that is both, a card form and a wallet, says so through
 * {@see ExpressPaymentModuleInterface::isAlsoOfferedAtCheckout()}.
 *
 * One rule, read by the guard of the ordinary placement and by the theme's order button,
 * so the button a buyer sees and the order the shop accepts never disagree.
 */
final class CheckoutPaymentOffer
{
    public static function canBePaidAtCheckout(object $paymentModuleInstance): bool
    {
        return !$paymentModuleInstance instanceof ExpressPaymentModuleInterface
            || $paymentModuleInstance->isAlsoOfferedAtCheckout();
    }
}
