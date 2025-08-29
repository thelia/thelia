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
use Thelia\Domain\Shipping\DTO\DeliveryModuleWithOptionDTO;
use Thelia\Domain\Shipping\DTO\PostageEstimateView;
use Thelia\Domain\Shipping\Service\DeliveryModuleEligibilityChecker;
use Thelia\Domain\Shipping\Service\DeliveryModuleLookupService;
use Thelia\Domain\Shipping\Service\DeliveryModuleResourceBuilder;
use Thelia\Domain\Shipping\Service\DeliveryOptionsProvider;
use Thelia\Domain\Shipping\Service\DeliverySessionStorage;
use Thelia\Domain\Shipping\Service\DeliverySetupService;
use Thelia\Domain\Shipping\Service\PostageEstimator;
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
    ) {
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
        $country = $this->handleCountry($cart, $country);

        [$address, $country, $state] = $this->resolveAddressContext($cart, $country, $state, $addressId);

        if (null === $country) {
            return new PostageEstimateView(null, null, null);
        }

        $estimate = $this->postageEstimator->estimatePostageForCountry($cart, $country, $state);

        $amountHt = isset($estimate['postage']) ? (float) $estimate['postage'] : null;
        $tax = isset($estimate['tax']) ? (float) $estimate['tax'] : null;
        $totalTtc = null;

        if (null !== $amountHt && null !== $tax) {
            $totalTtc = $amountHt + $tax;
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
     * Set the delivery address on the cart (setter + save).
     */
    public function chooseDeliveryAddress(Cart $cart, int $addressId): void
    {
        $cart->setAddressDeliveryId($addressId)->save();
    }

    /**
     * Set the delivery module on the cart (setter + save).
     */
    public function chooseDeliveryModule(Cart $cart, int $moduleId): void
    {
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
        [$address, $country, $state] = $this->resolveAddressContext($cart, $country, $state, $addressId);

        $modules = $this->getActiveDeliveryModules();

        $result = [];
        foreach ($modules as $module) {
            $resource = $this->resourceBuilder->build($module, $cart, $address, $country, $state, true);
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
        [$address, $country, $state] = $this->resolveAddressContext($cart, $country, $state, $addressId);
        $country = $this->handleCountry($cart, $country);
        $validModules = [];
        $modules = $this->getActiveDeliveryModules();

        foreach ($modules as $module) {
            if (!$this->eligibilityChecker->isEligible($module, $cart, $country, $state)) {
                continue;
            }
            $resourceDeliveryModule = $this->deliveryModuleResourceBuilder->build(
                $module,
                $cart,
                $address,
                $country,
                $state,
                true
            );
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
     * Resolve the trio (Address?, Country?, State?) from arguments and/or the cart.
     *
     * Rules:
     * - If country is provided, use the provided (country, state) over address.
     * - Else, if addressId is provided (or present on the cart), resolve through the address.
     * - Else, return (null, null, null).
     *
     * @return array{0: ?\Thelia\Model\Address, 1: ?Country, 2: ?State}
     */
    private function resolveAddressContext(
        Cart $cart,
        ?Country $country,
        ?State $state,
        ?int $addressId,
    ): array {
        if (null !== $country) {
            // Explicit (country/state) context over address
            $address = null;

            return [$address, $country, $state];
        }

        $resolvedAddressId = $addressId ?? $cart->getAddressDeliveryId();
        if (null === $resolvedAddressId) {
            return [null, null, null];
        }

        $address = AddressQuery::create()->findPk($resolvedAddressId);
        if (null === $address) {
            return [null, null, null];
        }

        return [$address, $address->getCountry(), $address->getState()];
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
            ->find()
            ->getData();
    }

    private function handleCountry(Cart $cart, ?Country $country): Country
    {
        if ($country === null) {
            $addressDeliveryId = $cart->getAddressDeliveryId();
            if ($addressDeliveryId !== null) {
                $addressDelivery = AddressQuery::create()->findPk($addressDeliveryId);
                $country = $addressDelivery?->getCountry();
            }
        }

        if ($country === null) {
            $country = CountryQuery::create()->findOneByByDefault(1);
        }

        return $country;
    }
}
