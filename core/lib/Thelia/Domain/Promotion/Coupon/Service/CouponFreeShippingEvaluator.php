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

namespace Thelia\Domain\Promotion\Coupon\Service;

use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponModule;

class CouponFreeShippingEvaluator
{
    public function __construct(protected CouponManager $couponManager)
    {
    }

    public function isCouponRemovingPostage(int $countryId, int $deliveryModuleId): bool
    {
        $couponsKept = $this->couponManager->getCouponsKept();

        if ([] === $couponsKept) {
            return false;
        }

        /** @var CouponInterface $coupon */
        foreach ($couponsKept as $coupon) {
            if (!$coupon->isRemovingPostage()) {
                continue;
            }

            // Check if the delivery country is on the list of countries for which delivery is free
            // If the list is empty, the shipping is free for all countries.
            $couponCountries = $coupon->getFreeShippingForCountries();

            if (!$couponCountries->isEmpty()) {
                $countryValid = false;

                /** @var CouponCountry $couponCountry */
                foreach ($couponCountries as $couponCountry) {
                    if ($countryId === $couponCountry->getCountryId()) {
                        $countryValid = true;
                        break;
                    }
                }

                if (!$countryValid) {
                    continue;
                }
            }

            // Check if the shipping method is on the list of methods for which delivery is free
            // If the list is empty, the shipping is free for all methods.
            $couponModules = $coupon->getFreeShippingForModules();

            if (!$couponModules->isEmpty()) {
                $moduleValid = false;

                /** @var CouponModule $couponModule */
                foreach ($couponModules as $couponModule) {
                    if ($deliveryModuleId === $couponModule->getModuleId()) {
                        $moduleValid = true;
                        break;
                    }
                }

                if (!$moduleValid) {
                    continue;
                }
            }

            // All conditions are met, the shipping is free!
            return true;
        }

        return false;
    }
}
