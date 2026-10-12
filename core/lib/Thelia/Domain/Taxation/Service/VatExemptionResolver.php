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

use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Legal\CompanyIdentifier;
use Thelia\Domain\Localization\Service\EuropeanUnionCountries;
use Thelia\Domain\Taxation\Enum\VatExemptionMode;
use Thelia\Domain\Taxation\Enum\VatExemptionState;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;

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
final class VatExemptionResolver implements ResetInterface
{
    /** @var array<int, ?string> */
    private array $isoCodeByCountryId = [];

    /** @var array<string, ?string> */
    private array $shopIsoCodeByStoreCountry = [];

    public function __construct(
        private readonly EuropeanUnionCountries $europeanUnionCountries,
    ) {
    }

    public function reset(): void
    {
        $this->isoCodeByCountryId = [];
        $this->shopIsoCodeByStoreCountry = [];
    }

    public function isExemptedForCart(Cart $cart): bool
    {
        return VatExemptionState::EXEMPTED === $this->stateForCart($cart);
    }

    public function stateForCart(Cart $cart): VatExemptionState
    {
        if (VatExemptionMode::VERIFIED_VAT_NUMBER !== VatExemptionMode::fromShopConfiguration()) {
            return VatExemptionState::NOT_APPLICABLE;
        }

        // A shop that is itself out of the VAT scope charges none to begin with,
        // and has no VAT to reverse onto its buyer.
        if (ConfigQuery::isStoreVatExempt()) {
            return VatExemptionState::NOT_APPLICABLE;
        }

        $invoiceAddress = $cart->getCartAddressRelatedByAddressInvoiceId();

        if (!$invoiceAddress instanceof CartAddress || '' === trim((string) $invoiceAddress->getVatNumber())) {
            return VatExemptionState::NOT_APPLICABLE;
        }

        $buyerCountryCode = $this->isoCodeOf($invoiceAddress->getCountryId());

        if (!$this->crossesABorderOfTheUnion($buyerCountryCode)) {
            return VatExemptionState::NOT_APPLICABLE;
        }

        if (!$this->numberBelongsTo((string) $invoiceAddress->getVatNumber(), (string) $buyerCountryCode)) {
            return VatExemptionState::NOT_VERIFIED;
        }

        $verifiedAt = $invoiceAddress->getVatVerifiedAt();

        if (null === $verifiedAt) {
            return VatExemptionState::NOT_VERIFIED;
        }

        return $this->hasExpired($verifiedAt) ? VatExemptionState::VERIFICATION_EXPIRED : VatExemptionState::EXEMPTED;
    }

    /**
     * A verification vouches for a number in the country that issued it: a verifier that
     * ignores the country it is given must not be able to exempt an address of another.
     */
    private function numberBelongsTo(string $vatNumber, string $countryCode): bool
    {
        $normalized = CompanyIdentifier::normalizeVatNumber($vatNumber) ?? '';

        foreach ($this->europeanUnionCountries->vatPrefixesFor($countryCode) as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function crossesABorderOfTheUnion(?string $buyerCountryCode): bool
    {
        if (null === $buyerCountryCode || !$this->europeanUnionCountries->isMember($buyerCountryCode)) {
            return false;
        }

        $shopCountryCode = $this->shopIsoCode();

        // A buyer established in the shop's own country pays its VAT like anyone
        // else: reverse charge only crosses a border.
        return null !== $shopCountryCode
            && strtoupper($shopCountryCode) !== strtoupper($buyerCountryCode);
    }

    private function isoCodeOf(?int $countryId): ?string
    {
        if (null === $countryId) {
            return null;
        }

        if (!\array_key_exists($countryId, $this->isoCodeByCountryId)) {
            $this->isoCodeByCountryId[$countryId] = CountryQuery::create()->findPk($countryId)?->getIsoalpha2();
        }

        return $this->isoCodeByCountryId[$countryId];
    }

    private function shopIsoCode(): ?string
    {
        $storeCountry = (string) ConfigQuery::getStoreCountry();

        if (!\array_key_exists($storeCountry, $this->shopIsoCodeByStoreCountry)) {
            try {
                $this->shopIsoCodeByStoreCountry[$storeCountry] = Country::getShopLocation()->getIsoalpha2();
            } catch (\LogicException) {
                $this->shopIsoCodeByStoreCountry[$storeCountry] = null;
            }
        }

        return $this->shopIsoCodeByStoreCountry[$storeCountry];
    }

    private function hasExpired(\DateTimeInterface $verifiedAt): bool
    {
        $expiresAt = \DateTimeImmutable::createFromInterface($verifiedAt)
            ->modify(\sprintf('+%d days', ConfigQuery::getVatVerificationLifetimeDays()));

        return $expiresAt < new \DateTimeImmutable();
    }
}
