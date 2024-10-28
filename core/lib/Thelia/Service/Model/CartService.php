<?php

namespace Thelia\Service\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Coupon\CouponManager;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponModule;
use Thelia\Model\ModuleQuery;
use Thelia\Model\State;
use Thelia\Module\BaseModule;
use Thelia\Module\Exception\DeliveryException;

class CartService
{
    public function __construct(private CouponManager $couponManager,private Session $session)
    {
    }

    /**
     * Return the minimum expected postage for a cart in a given country.
     *
     * @return array
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function getEstimatedPostageForCountry(Cart $cart, Country $country, State $state = null)
    {
        $orderSession = $this->session->getOrder();
        $deliveryModules = [];

        if ($deliveryModule = ModuleQuery::create()->findPk($orderSession->getDeliveryModuleId())) {
            $deliveryModules[] = $deliveryModule;
        }

        if (empty($deliveryModules)) {
            $deliveryModules = ModuleQuery::create()
                ->filterByActivate(1)
                ->filterByType(BaseModule::DELIVERY_MODULE_TYPE, Criteria::EQUAL)
                ->find();
        }

        $virtual = $cart->isVirtual();
        $postage = null;
        $postageTax = null;

        /** @var \Thelia\Model\Module $deliveryModule */
        foreach ($deliveryModules as $deliveryModule) {
            $areaDeliveryModule = AreaDeliveryModuleQuery::create()
                ->findByCountryAndModule($country, $deliveryModule, $state);

            if (null === $areaDeliveryModule && false === $virtual) {
                continue;
            }

            $moduleInstance = $deliveryModule->getDeliveryModuleInstance($this->container);

            if (true === $virtual && false === $moduleInstance->handleVirtualProductDelivery()) {
                continue;
            }

            try {
                $deliveryPostageEvent = new DeliveryPostageEvent($moduleInstance, $cart, null, $country, $state);
                $this->dispatcher->dispatch(
                    $deliveryPostageEvent,
                    TheliaEvents::MODULE_DELIVERY_GET_POSTAGE
                );

                if ($deliveryPostageEvent->isValidModule()) {
                    $modulePostage = $deliveryPostageEvent->getPostage();

                    if (null === $postage || $postage > $modulePostage->getAmount()) {
                        $postage = $modulePostage->getAmount() - $modulePostage->getAmountTax();
                        $postageTax = $modulePostage->getAmountTax();
                    }
                }
            } catch (DeliveryException $ex) {
                // Module is not available
            }
        }

        if ($this->isCouponRemovingPostage($country->getId(), $deliveryModule->getId())) {
            $postage = 0;
            $postageTax = 0;
        }

        return [
            'postage' => $postage,
            'tax' => $postageTax
        ];
    }

    private function isCouponRemovingPostage(int $countryId, int $deliveryModuleId)
    {
        $couponsKept = $this->couponManager->getCouponsKept();

        if (\count($couponsKept) == 0) {
            return false;
        }

        /** @var CouponInterface $coupon */
        foreach ($couponsKept as $coupon) {
            if (!$coupon->isRemovingPostage()) {
                continue;
            }

            // Check if delivery country is on the list of countries for which delivery is free
            // If the list is empty, the shipping is free for all countries.
            $couponCountries = $coupon->getFreeShippingForCountries();

            if (!$couponCountries->isEmpty()) {
                $countryValid = false;

                /** @var CouponCountry $couponCountry */
                foreach ($couponCountries as $couponCountry) {
                    if ($countryId == $couponCountry->getCountryId()) {
                        $countryValid = true;
                        break;
                    }
                }

                if (!$countryValid) {
                    continue;
                }
            }

            // Check if shipping method is on the list of methods for which delivery is free
            // If the list is empty, the shipping is free for all methods.
            $couponModules = $coupon->getFreeShippingForModules();

            if (!$couponModules->isEmpty()) {
                $moduleValid = false;

                /** @var CouponModule $couponModule */
                foreach ($couponModules as $couponModule) {
                    if ($deliveryModuleId == $couponModule->getModuleId()) {
                        $moduleValid = true;
                        break;
                    }
                }

                if (!$moduleValid) {
                    continue;
                }
            }

            // All conditions are met, the shipping is free !
            return true;
        }

        return false;
    }
}
