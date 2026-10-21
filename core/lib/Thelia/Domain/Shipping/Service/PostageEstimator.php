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

namespace Thelia\Domain\Shipping\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Promotion\Coupon\Service\CouponFreeShippingEvaluator;
use Thelia\Domain\Shipping\DTO\EstimatedPostageDTO;
use Thelia\Domain\Shipping\ProductRule\CartShippingRules;
use Thelia\Domain\Shipping\ProductRule\ProductShippingRuleQuoter;
use Thelia\Log\Tlog;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\State;
use Thelia\Module\BaseModule;
use Thelia\Module\Exception\DeliveryException;

class PostageEstimator
{
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        protected ContainerInterface $container,
        protected Session $session,
        protected CouponFreeShippingEvaluator $couponFreeShippingEvaluator,
        protected PostageTaxBreakdownCalculator $postageTaxBreakdownCalculator,
        protected ?ProductShippingRuleQuoter $productShippingRuleQuoter = null,
        protected ?CartShippingRules $cartShippingRules = null,
    ) {
    }

    /**
     * The postal code of the cart's delivery address when that address is in the given
     * country, null otherwise. The estimate and the lists of carriers both read it, so that
     * they never judge two different codes for one cart and one country.
     */
    public function cartZipCodeIn(Cart $cart, Country $country): ?string
    {
        $deliveryAddress = $cart->getCartAddressRelatedByAddressDeliveryId();

        return null !== $deliveryAddress && $deliveryAddress->getCountryId() === $country->getId()
            ? $deliveryAddress->getZipcode()
            : null;
    }

    /**
     * Return the minimum expected postage, tax included, for a cart in a given country.
     *
     * Without a postal code, the one of the cart's delivery address is used when that
     * address is in the country estimated for: an estimate never offers a carrier the
     * checkout will refuse for that address.
     *
     * @param string|null $zipCode the postal code the postal code rules of the zones judge;
     *                             null means the postal code of the cart's delivery address
     *                             when that address is in $country, and no postal code
     *                             otherwise. There is no way to ask for "no postal code" for
     *                             a cart whose address is in the country.
     *
     * @throws PropelException
     */
    public function estimatePostageForCountry(
        Cart $cart,
        Country $country,
        ?State $state = null,
        ?string $zipCode = null,
    ): EstimatedPostageDTO {
        $zipCode ??= $this->cartZipCodeIn($cart, $country);

        $orderSession = $this->session->getOrder();

        $deliveryModules = $this->resolveCandidateDeliveryModules(
            $orderSession->getDeliveryModuleId(),
            $cart,
        );

        $virtual = $cart->isVirtual();

        $bestPostageAmount = null;
        $bestPostageTax = null;
        $bestModuleId = null;
        $bestModule = null;

        /** @var Module $deliveryModule */
        foreach ($deliveryModules as $deliveryModule) {
            if (!$this->isModuleEligibleForCountryAndCart($deliveryModule, $country, $state, $virtual, $zipCode)) {
                continue;
            }

            // A carrier a product of the cart refuses is not a carrier the buyer can pick.
            if ($this->cartShippingRules?->for($cart)->excludes((int) $deliveryModule->getId())) {
                continue;
            }

            $modulePostage = $this->computePostageForModule($deliveryModule, $cart, $country, $state);

            if ($modulePostage instanceof OrderPostage) {
                $amountTtc = $modulePostage->getAmount();
                $tax = $modulePostage->getAmountTax();

                if (null === $bestPostageAmount || $bestPostageAmount > $amountTtc) {
                    $bestPostageAmount = $amountTtc;
                    $bestPostageTax = $tax;
                    $bestModuleId = $deliveryModule->getId();
                    $bestModule = $deliveryModule;
                }
            }
        }

        if (null !== $bestModuleId && $this->couponFreeShippingEvaluator->isCouponRemovingPostage($country->getId(), $bestModuleId)) {
            // The coupon offers the carrier, not the surcharges of the products.
            $surcharge = $bestModule instanceof Module
                ? $this->productShippingRuleQuoter?->surchargeLeftByAFreeShippingCoupon($bestModule, $cart, $country)
                : null;

            $bestPostageAmount = $surcharge?->getAmount() ?? 0.0;
            $bestPostageTax = $surcharge?->getAmountTax() ?? 0.0;
        }

        return new EstimatedPostageDTO($bestPostageAmount, $bestPostageTax);
    }

    /**
     * @return Module[]
     *
     * @throws PropelException
     */
    private function resolveCandidateDeliveryModules(?int $sessionDeliveryModuleId, Cart $cart): array
    {
        $deliveryModules = [];

        // The carrier of the session, unless a product of the cart refuses it: the
        // estimate then falls back on the carriers the buyer can still pick.
        if (null !== $sessionDeliveryModuleId && !$this->cartShippingRules?->for($cart)->excludes($sessionDeliveryModuleId)) {
            if ($deliveryModule = ModuleQuery::create()->findPk($sessionDeliveryModuleId)) {
                $deliveryModules[] = $deliveryModule;
            }
        }

        if ([] === $deliveryModules) {
            $deliveryModules = ModuleQuery::create()
                ->filterByActivate(1)
                ->filterByType(BaseModule::DELIVERY_MODULE_TYPE, Criteria::EQUAL)
                ->find()
                ->getData();
        }

        return $deliveryModules;
    }

    /**
     * @throws PropelException
     */
    private function isModuleEligibleForCountryAndCart(
        Module $deliveryModule,
        Country $country,
        ?State $state,
        bool $virtual,
        ?string $zipCode,
    ): bool {
        $areaDeliveryModule = AreaDeliveryModuleQuery::create()
            ->findByCountryAndModule($country, $deliveryModule, $state, $zipCode);

        if (null === $areaDeliveryModule && false === $virtual) {
            return false;
        }

        $moduleInstance = $deliveryModule->getDeliveryModuleInstance($this->container);

        return !(true === $virtual && false === $moduleInstance->handleVirtualProductDelivery());
    }

    private function computePostageForModule(
        Module $deliveryModule,
        Cart $cart,
        Country $country,
        ?State $state,
    ): ?OrderPostage {
        try {
            $moduleInstance = $deliveryModule->getDeliveryModuleInstance($this->container);

            $askCarrier = function (Cart $quotedCart) use ($moduleInstance, $country, $state): ?OrderPostage {
                $deliveryPostageEvent = new DeliveryPostageEvent($moduleInstance, $quotedCart, null, $country, $state);
                $this->eventDispatcher->dispatch(
                    $deliveryPostageEvent,
                    TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
                );

                return $deliveryPostageEvent->isValidModule() && $deliveryPostageEvent->getPostage() instanceof OrderPostage
                    ? $deliveryPostageEvent->getPostage()
                    : null;
            };

            $postage = null !== $this->productShippingRuleQuoter
                ? $this->productShippingRuleQuoter->quote($deliveryModule, $cart, $country, $state, $askCarrier)
                : $askCarrier($cart);

            if ($postage instanceof OrderPostage) {
                // Same post-processing as the checkout, so an estimate and
                // the cart it turns into never announce two different taxes.
                $this->postageTaxBreakdownCalculator->applyToPostage($postage, $cart, $country, $state);

                return $postage;
            }
        } catch (DeliveryException) {
            Tlog::getInstance()->error(
                \sprintf('Delivery module %s is not available', $deliveryModule->getCode()),
            );
        }

        return null;
    }
}
