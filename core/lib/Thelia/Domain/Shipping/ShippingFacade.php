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

namespace Thelia\Domain\Shipping;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Thelia\Api\Resource\DeliveryModule as DeliveryModuleResource;
use Thelia\Api\State\Collection\DeliveryModuleCollection;
use Thelia\Domain\Cart\Service\CartAddressService;
use Thelia\Domain\Cart\Service\CartGuard;
use Thelia\Domain\Checkout\Exception\DeliveryExcludedByProductException;
use Thelia\Domain\Shipping\DTO\DeliveryModuleWithOptionDTO;
use Thelia\Domain\Shipping\DTO\PostageEstimateView;
use Thelia\Domain\Shipping\ProductRule\CartShippingRules;
use Thelia\Domain\Shipping\Service\DeliveryModuleEligibilityChecker;
use Thelia\Domain\Shipping\Service\DeliveryModuleLookupService;
use Thelia\Domain\Shipping\Service\DeliveryModuleResourceBuilder;
use Thelia\Domain\Shipping\Service\DeliveryOptionsProvider;
use Thelia\Domain\Shipping\Service\DeliverySessionStorage;
use Thelia\Domain\Shipping\Service\DeliverySetupService;
use Thelia\Domain\Shipping\Service\PostageEstimator;
use Thelia\Domain\Shipping\ZipCode\ZipCodePattern;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\State;
use Thelia\Module\BaseModule;

final readonly class ShippingFacade
{
    public function __construct(
        private DeliveryModuleResourceBuilder $resourceBuilder,
        private DeliveryModuleLookupService $lookupService,
        private DeliverySessionStorage $sessionStorage,
        private DeliverySetupService $deliverySetupService,
        private PostageEstimator $postageEstimator,
        private DeliveryModuleEligibilityChecker $eligibilityChecker,
        private DeliveryOptionsProvider $deliveryOptionsProvider,
        private DeliveryModuleResourceBuilder $deliveryModuleResourceBuilder,
        private CartAddressService $cartAddressService,
        private ?CartShippingRules $cartShippingRules = null,
        private ?CartGuard $cartGuard = null,
    ) {
    }

    /**
     * The products of the cart no carrier can take, by title, for the delivery step to
     * name them instead of saying that no carrier is available. Empty while a carrier
     * is left.
     *
     * @return array<int, string> product id => title
     */
    public function productsBlockingEveryCarrier(Cart $cart, Country $country, ?State $state, string $locale): array
    {
        $productIds = $this->eligibilityChecker->productsBlockingEveryCarrier($cart, $country, $state, $this->postageEstimator->cartZipCodeIn($cart, $country));

        if ([] === $productIds || null === $this->cartShippingRules) {
            return [];
        }

        return $this->cartShippingRules->titlesOf($productIds, $locale);
    }

    /**
     * Estimate shipping for the cart (excl. tax and tax amount).
     *
     * - Uses the cart's delivery address if none is provided.
     */
    public function estimateCartPostage(
        Cart $cart,
        ?Country $country = null,
        ?State $state = null,
        ?int $addressId = null,
    ): PostageEstimateView {
        // The cart's copy first, with its state, and the default country only then, as the
        // lists of carriers do.
        [, $country, $state, $zipCode] = $this->resolveAddressContext($cart, $country, $state, $addressId);
        $country = $this->handleCountry($cart, $country);

        $estimate = $this->postageEstimator->estimatePostageForCountry($cart, $country, $state, $zipCode);

        $totalTtc = $estimate->getBestPostageAmount();
        $tax = $estimate->getBestPostageTax();
        $amountHt = null;

        if (null !== $totalTtc && null !== $tax) {
            $amountHt = $totalTtc - $tax;
        }

        return new PostageEstimateView($amountHt, $tax, $totalTtc);
    }

    /**
     * Front helper: expose resolved delivery country for the given cart (with shop default fallback).
     */
    public function getCountryForCart(Cart $cart): ?Country
    {
        return $this->handleCountry($cart, null);
    }

    /**
     * Set the delivery address on the cart, from the id of a customer `address`.
     *
     * The cart does not point at `address` but at its own copy of it, in
     * `cart_address`, so the copy is made (or refreshed) on the way.
     */
    public function chooseDeliveryAddress(Cart $cart, int $addressId): void
    {
        $address = AddressQuery::create()->findPk($addressId);

        if (null === $address) {
            throw new \InvalidArgumentException(\sprintf('Address #%d not found', $addressId));
        }

        $cartAddress = $this->cartAddressService->getOrCreateCartAddressFromAddress(
            $address,
            $cart->getCartAddressRelatedByAddressDeliveryId(),
        );

        $cart->setAddressDeliveryId($cartAddress->getId())->save();
    }

    /**
     * Set the delivery module on the cart (setter + save).
     *
     * @throws DeliveryExcludedByProductException when a product of the cart refuses the carrier
     */
    public function chooseDeliveryModule(Cart $cart, int $moduleId): void
    {
        $this->cartGuard?->refuseACarrierTheProductsExclude($cart, $moduleId);

        $cart->setDeliveryModuleId($moduleId)->save();
    }

    /**
     * Setup virtual delivery (module + optional customer default address + shipping recalculation).
     */
    public function setupVirtualDelivery(Cart $cart): void
    {
        $this->deliverySetupService->setupVirtualDelivery($cart);
    }

    /**
     * Set cart delivery address to the customer's default address.
     */
    public function setCustomerDefaultDeliveryAddress(Cart $cart): void
    {
        $this->deliverySetupService->setCustomerDefaultDeliveryAddress($cart);
    }

    /**
     * Find the deliveryMode for a module in the given collection.
     */
    public function findDeliveryModeByModuleId(int $moduleId, DeliveryModuleCollection $collection): ?string
    {
        return $this->lookupService->findDeliveryModeByModuleId($moduleId, $collection);
    }

    /**
     * Session-based "delivery" storage helpers.
     */
    public function setDeliveryData(string $key, mixed $value): void
    {
        $this->sessionStorage->setDeliveryData($key, $value);
    }

    public function getDeliveryData(string $key, mixed $default = null): mixed
    {
        return $this->sessionStorage->getDeliveryData($key, $default);
    }

    public function getAllDeliveryData(): array
    {
        return $this->sessionStorage->getAllDeliveryData();
    }

    public function clearDeliveryData(): void
    {
        $this->sessionStorage->clearDeliveryData();
    }

    /**
     * List valid delivery methods for the given cart/context.
     * If no country given, will use the cart's delivery address country.
     * If no address on cart, will use the shop default country.
     *
     * @return DeliveryModuleResource[] API resources ready to use
     *
     * @throws PropelException
     */
    public function listValidMethodsAsResourceApi(
        Cart $cart,
        ?Country $country = null,
        ?State $state = null,
        ?int $addressId = null,
    ): array {
        [$address, $country, $state, $zipCode] = $this->resolveAddressContext($cart, $country, $state, $addressId);
        $country = $this->handleCountry($cart, $country);

        $modules = $this->getActiveDeliveryModules();

        $result = [];
        foreach ($modules as $module) {
            $resource = $this->resourceBuilder->build($module, $cart, $address, $country, $state, true, $zipCode);
            if (null !== $resource) {
                $result[] = $resource;
            }
        }

        return $result;
    }

    /**
     * @return array<int, DeliveryModuleWithOptionDTO> List of valid delivery modules
     */
    public function listValidMethods(
        Cart $cart,
        ?Country $country = null,
        ?State $state = null,
        ?int $addressId = null,
    ): array {
        [$address, $country, $state, $zipCode] = $this->resolveAddressContext($cart, $country, $state, $addressId);
        $country = $this->handleCountry($cart, $country);
        $validModules = [];
        $modules = $this->getActiveDeliveryModules();

        foreach ($modules as $module) {
            // Only valid: the builder judges the eligibility, and returns null for a carrier
            // that does not serve the address before asking it for a price.
            $resourceDeliveryModule = $this->deliveryModuleResourceBuilder->build(
                $module,
                $cart,
                $address,
                $country,
                $state,
                true,
                $zipCode,
            );
            if (null === $resourceDeliveryModule) {
                continue;
            }
            $validModules[] = new DeliveryModuleWithOptionDTO(
                $resourceDeliveryModule,
                $this->deliveryOptionsProvider->getOptions(
                    $module,
                    $address,
                    $cart,
                    $country,
                    $state
                )
            );
        }

        return $validModules;
    }

    /**
     * The postal code of the cart's delivery address when it is the reason no carrier is
     * offered: no carrier is offered, and some active carrier would be without it (its zones
     * serve the country and it quotes the cart) but the postal code rules of its zones refuse
     * that code. Null otherwise, including when carriers are offered.
     *
     * Each carrier is asked its price for the cart (TheliaEvents::MODULE_DELIVERY_GET_POSTAGE),
     * those the postal code turned away included.
     *
     * A front office reads it to explain an empty list of carriers instead of only
     * showing one.
     */
    public function postalCodeWithoutCarrier(Cart $cart): ?string
    {
        [$address, $country, $state, $zipCode] = $this->resolveAddressContext($cart, null, null, null);
        $country = $this->handleCountry($cart, $country);

        // Nothing to name: an unknown code, a placeholder or an uncertain one.
        $nameable = ZipCodePattern::nameable($zipCode);

        if (null === $nameable) {
            return null;
        }

        $refusedByThePostalCode = [];

        foreach ($this->getActiveDeliveryModules() as $module) {
            if ($this->eligibilityChecker->isEligible($module, $cart, $country, $state, $zipCode)) {
                // A carrier that serves the address and quotes the cart is offered. One that
                // serves it but does not quote the cart is not a carrier the postal code took away.
                if (null !== $this->resourceBuilder->build($module, $cart, $address, $country, $state, true, $zipCode)) {
                    return null;
                }

                continue;
            }

            $refusedByThePostalCode[] = $module;
        }

        // Asked last, and only of the carriers the rules turned away: a carrier that would not
        // quote this cart anyway is not one the postal code took away.
        foreach ($refusedByThePostalCode as $module) {
            if (null !== $this->resourceBuilder->build($module, $cart, $address, $country, $state, true)) {
                return $nameable;
            }
        }

        return null;
    }

    /**
     * Resolve the trio (Address?, Country?, State?) from arguments and/or the cart.
     *
     * Rules:
     * - If country is provided, use the provided (country, state) over address.
     * - Else, if addressId names an address of the cart's customer, resolve through it.
     * - Else, resolve through the cart's own copy of its delivery address: its country,
     *   state and postal code, with the customer address behind it.
     * - Else, return (null, null, null, null).
     *
     * The postal code comes with the address it belongs to: the cart's own copy for the
     * cart's address, the place the order leaves for, also when no customer address stands
     * behind the copy (its customer was anonymized, or a module wrote the copy itself). An
     * `addressId` gives its postal code only when it is an
     * address of the cart's customer in the country asked for; otherwise, as for a country
     * given alone, the postal code of the cart's address is read when that address is in
     * the country.
     *
     * @param int|null $addressId id of a customer `address`
     *
     * @return array{0: ?Address, 1: ?Country, 2: ?State, 3: ?string}
     */
    private function resolveAddressContext(
        Cart $cart,
        ?Country $country,
        ?State $state,
        ?int $addressId,
    ): array {
        if (null !== $country) {
            // Explicit (country/state) context over address; an address of the cart's
            // customer still gives its postal code when it is in that country, as the API
            // passes both.
            // An address in another country says nothing of this one: as for an address of
            // someone else, the cart's address speaks when it is in the country.
            $address = null === $addressId ? null : $this->addressOfTheCartCustomer($cart, $addressId);

            if (null === $address || $address->getCountryId() !== $country->getId()) {
                return [null, $country, $state, $this->postageEstimator->cartZipCodeIn($cart, $country)];
            }

            return [null, $country, $state, $address->getZipcode()];
        }

        // An address of someone else, or none at all, is not handed to the modules: the list
        // is then the one of the cart, as without an address.
        $address = null === $addressId ? null : $this->addressOfTheCartCustomer($cart, $addressId);

        if (null !== $address) {
            return [$address, $address->getCountry(), $address->getState(), $address->getZipcode()];
        }

        $cartAddress = $cart->getCartAddressRelatedByAddressDeliveryId();
        $address = $cartAddress?->getAddress();

        if (null === $address) {
            // No customer address behind the copy (its customer was anonymized, or a module
            // wrote the copy itself): the copy's country, which handleCountry() reads, its
            // state and its postal code.
            return [null, null, $cartAddress?->getState(), $cartAddress?->getZipcode()];
        }

        // The country, the state and the postal code of the copy, the place the order leaves
        // for and the one CartGuard judges: the customer address may have moved since it was
        // chosen. The customer address is still the one handed to the modules.
        return [$address, $cartAddress->getCountry(), $cartAddress->getState(), $cartAddress->getZipcode()];
    }

    /**
     * The address an `addressId` names for the carriers, when it is another address of the
     * customer of the cart than the one the cart's copy was taken from.
     *
     * An id comes with a request: the postal code of an address of someone else would
     * otherwise be read out through the carriers offered for it. The source of the copy is
     * the copy's to judge, as CartGuard judges the order: the address may have been edited
     * since it was chosen. A guest row is reused by whoever types the same e-mail address
     * (CustomerGuestRegistrationService), so it carries the addresses of earlier buyers: the
     * only address a guest owns for sure is the source of the copy, so a guest names none.
     */
    private function addressOfTheCartCustomer(Cart $cart, int $addressId): ?Address
    {
        $customer = $cart->getCustomer();

        if (null === $customer || $customer->isGuest()) {
            return null;
        }

        if ($addressId === $cart->getCartAddressRelatedByAddressDeliveryId()?->getAddressId()) {
            return null;
        }

        return AddressQuery::create()
            ->filterById($addressId)
            ->filterByCustomerId($customer->getId())
            ->findOne();
    }

    /**
     * Active delivery modules.
     *
     * @return Module[]
     */
    private function getActiveDeliveryModules(): array
    {
        return ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE, Criteria::EQUAL)
            ->orderByPosition()
            ->find()
            ->getData();
    }

    private function handleCountry(Cart $cart, ?Country $country): Country
    {
        if ($country === null) {
            // Read from the cart's own copy of the address: it carries a country
            // of its own, so an address typed in at checkout and never saved to
            // the customer account still ships to the right place.
            $country = $cart->getCartAddressRelatedByAddressDeliveryId()?->getCountry();
        }

        if ($country === null) {
            $country = CountryQuery::create()->findOneByByDefault(1);
        }

        return $country;
    }
}
