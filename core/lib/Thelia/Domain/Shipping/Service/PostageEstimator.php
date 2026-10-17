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
     * Return the minimum expected postage, tax included, for a cart in a given country.
     *
     * @throws PropelException
     */
    public function estimatePostageForCountry(
        Cart $cart,
        Country $country,
        ?State $state = null,
    ): EstimatedPostageDTO {
        $orderSession = $this->session->getOrder();

        $deliveryModules = $this->resolveCandidateDeliveryModules(
            $orderSession->getDeliveryModuleId()
        );

        $virtual = $cart->isVirtual();

        $bestPostageAmount = null;
        $bestPostageTax = null;
        $bestModuleId = null;
        $bestModule = null;

        /** @var Module $deliveryModule */
        foreach ($deliveryModules as $deliveryModule) {
            if (!$this->isModuleEligibleForCountryAndCart($deliveryModule, $country, $state, $virtual)) {
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
    private function resolveCandidateDeliveryModules(?int $sessionDeliveryModuleId): array
    {
        $deliveryModules = [];

        if (null !== $sessionDeliveryModuleId) {
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
    ): bool {
        $areaDeliveryModule = AreaDeliveryModuleQuery::create()
            ->findByCountryAndModule($country, $deliveryModule, $state);

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
