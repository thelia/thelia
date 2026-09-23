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

namespace Thelia\Domain\Taxation\Service;

use Thelia\Domain\Localization\Service\EuropeanUnionCountries;
use Thelia\Domain\Taxation\Enum\VatExemptionMode;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Order;

/**
 * Whether an order leaves without VAT because its buyer accounts for it.
 *
 * Reverse charge applies when the seller and the buyer are both liable for VAT
 * in the Union but in two different member states. That is a fact about the
 * billing address, not the delivery one: goods may ship to a warehouse
 * anywhere, the invoice is what the tax follows.
 *
 * Everything this needs is passed in, and nothing is read from the session: the
 * same cart answers the same way from a controller, from the API and from a
 * console command. The shop country is the one configured on the store rather
 * than the default taxation country, because the rule is about where the seller
 * is established.
 */
final readonly class VatExemptionResolver
{
    public function __construct(
        private EuropeanUnionCountries $europeanUnionCountries,
    ) {
    }

    public function isExemptedForCart(Cart $cart): bool
    {
        if (VatExemptionMode::VERIFIED_VAT_NUMBER !== VatExemptionMode::fromShopConfiguration()) {
            return false;
        }

        // A shop that is itself out of the VAT scope charges none to begin with,
        // and has no VAT to reverse onto its buyer.
        if (ConfigQuery::isStoreVatExempt()) {
            return false;
        }

        $invoiceAddress = $cart->getCartAddressRelatedByAddressInvoiceId();

        if (!$invoiceAddress instanceof CartAddress) {
            return false;
        }

        return $this->qualifies($invoiceAddress->getVatVerifiedAt(), $invoiceAddress->getCountry());
    }

    /**
     * What the order was invoiced on, read back rather than decided again.
     *
     * An order that left untaxed stays untaxed even after the number is revoked
     * or the shop turns the setting off, so this never re-runs the rule.
     */
    public function isExemptedForOrder(Order $order): bool
    {
        return $order->getVatExempted();
    }

    private function qualifies(?\DateTimeInterface $verifiedAt, ?Country $country): bool
    {
        if (null === $verifiedAt || $this->hasExpired($verifiedAt)) {
            return false;
        }

        $buyerCountryCode = $country?->getIsoalpha2();

        if (null === $buyerCountryCode || !$this->europeanUnionCountries->isMember($buyerCountryCode)) {
            return false;
        }

        $shopCountryCode = Country::getShopLocation()->getIsoalpha2();

        // A buyer established in the shop's own country pays its VAT like anyone
        // else: reverse charge only crosses a border.
        return null !== $shopCountryCode
            && strtoupper($shopCountryCode) !== strtoupper($buyerCountryCode);
    }

    private function hasExpired(\DateTimeInterface $verifiedAt): bool
    {
        $expiresAt = \DateTimeImmutable::createFromInterface($verifiedAt)
            ->modify(\sprintf('+%d days', ConfigQuery::getVatVerificationLifetimeDays()));

        return $expiresAt < new \DateTimeImmutable();
    }
}
