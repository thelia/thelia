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
    ) {
    }

    /**
     * Return the minimum expected postage for a cart in a given country.
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

        /** @var Module $deliveryModule */
        foreach ($deliveryModules as $deliveryModule) {
            if (!$this->isModuleEligibleForCountryAndCart($deliveryModule, $country, $state, $virtual)) {
                continue;
            }

            $modulePostage = $this->computePostageForModule($deliveryModule, $cart, $country, $state);

            if ($modulePostage instanceof OrderPostage) {
                $amountHt = $modulePostage->getAmount() - $modulePostage->getAmountTax();
                $tax = $modulePostage->getAmountTax();

                if (null === $bestPostageAmount || $bestPostageAmount > $amountHt) {
                    $bestPostageAmount = $amountHt;
                    $bestPostageTax = $tax;
                    $bestModuleId = $deliveryModule->getId();
                }
            }
        }

        if (null !== $bestModuleId && $this->couponFreeShippingEvaluator->isCouponRemovingPostage($country->getId(), $bestModuleId)) {
            $bestPostageAmount = 0.0;
            $bestPostageTax = 0.0;
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

            $deliveryPostageEvent = new DeliveryPostageEvent($moduleInstance, $cart, null, $country, $state);
            $this->eventDispatcher->dispatch(
                $deliveryPostageEvent,
                TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
            );

            if ($deliveryPostageEvent->isValidModule()) {
                $postage = $deliveryPostageEvent->getPostage();

                if ($postage instanceof OrderPostage) {
                    return $postage;
                }
            }
        } catch (DeliveryException) {
            Tlog::getInstance()->error(
                \sprintf('Delivery module %s is not available', $deliveryModule->getName()),
            );
        }

        return null;
    }
}
