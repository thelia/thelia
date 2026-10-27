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

use Thelia\Domain\Checkout\Enum\ExpressPaymentZone;
use Thelia\Model\ConfigQuery;
use Thelia\Module\ExpressPaymentModuleInterface;

/**
 * Whether the checkout may place an order itself with a payment module.
 *
 * Every payment module is listed among the payment methods of the checkout, an express one
 * included: choosing it turns the order button into the wallet's own button. An express one
 * is listed only while the shop shows its button in the checkout, or choosing it would leave
 * the buyer with no button at all. And the checkout never places an order with a module
 * that takes express payment only: only the
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

    /**
     * Whether the checkout may offer the module among its payment methods. A module the
     * checkout can place an order with always is; one that takes express payment only, when
     * both the shop and the module show express payment in the checkout.
     */
    public static function isOfferedAtCheckout(object $paymentModuleInstance): bool
    {
        if (!$paymentModuleInstance instanceof ExpressPaymentModuleInterface || self::canBePaidAtCheckout($paymentModuleInstance)) {
            return true;
        }

        return \in_array(ExpressPaymentZone::Checkout, $paymentModuleInstance->expressPaymentZones(), true)
            && \in_array(
                ExpressPaymentZone::Checkout,
                ExpressPaymentZone::listFromStoredValue(ConfigQuery::read(ExpressPaymentButtonCollector::ZONES_CONFIG_NAME, '')),
                true
            );
    }
}
