<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Coupon;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Model\Coupon;

/**
 * Manage how Coupons could interact with a Checkout
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponManager
{
    /** @var FacadeInterface Provides necessary value from Thelia */
    protected $facade = null;

    /** @var ContainerInterface Service Container */
    protected $container = null;

    /** @var array Available Coupons (Services) */
    protected $availableCoupons = array();

    /** @var array Available Conditions (Services) */
    protected $availableConditions = array();

    /**
     * Constructor
     *
     * @param ContainerInterface $container Service container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->facade = $container->get('thelia.facade');
    }

    /**
     * Get Discount for the given Coupons
     *
     * @api
     * @return float checkout discount
     */
    public function getDiscount()
    {
        $discount = 0.00;
        $coupons = $this->facade->getCurrentCoupons();
        if (count($coupons) > 0) {
            $couponsKept = $this->sortCoupons($coupons);

            $discount = $this->getEffect($couponsKept);

            // Just In Case test
            $checkoutTotalPrice = $this->facade->getCartTotalTaxPrice();
            if ($discount >= $checkoutTotalPrice) {
                $discount = $checkoutTotalPrice;
            }
        }

        return $discount;
    }

    /**
     * Check if there is a Coupon removing Postage
     * @return bool
     */
    public function isCouponRemovingPostage()
    {
        $coupons = $this->facade->getCurrentCoupons();
        if (count($coupons) == 0) {
            return false;
        }

        $couponsKept = $this->sortCoupons($coupons);

        /** @var CouponInterface $coupon */
        foreach ($couponsKept as $coupon) {
            if ($coupon->isRemovingPostage()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sort Coupon to keep
     * Coupon not cumulative cancels previous
     *
     * @param array $coupons CouponInterface to process
     *
     * @return array Array of CouponInterface sorted
     */
    protected function sortCoupons(array $coupons)
    {
        $couponsKept = array();

        /** @var CouponInterface $coupon */
        foreach ($coupons as $coupon) {
            if ($coupon && !$coupon->isExpired()) {
                if ($coupon->isCumulative()) {
                    if (isset($couponsKept[0])) {
                        /** @var CouponInterface $previousCoupon */
                        $previousCoupon = $couponsKept[0];
                        if ($previousCoupon->isCumulative()) {
                            // Add Coupon
                            $couponsKept[] = $coupon;
                        } else {
                            // Reset Coupons, add last
                            $couponsKept = array($coupon);
                        }
                    } else {
                        // Reset Coupons, add last
                        $couponsKept = array($coupon);
                    }
                } else {
                    // Reset Coupons, add last
                    $couponsKept = array($coupon);
                }
            }
        }

        $coupons = $couponsKept;
        $couponsKept = array();

        /** @var CouponInterface $coupon */
        foreach ($coupons as $coupon) {
            if ($coupon->isMatching($this->facade)) {
                $couponsKept[] = $coupon;
            }
        }

        return $couponsKept;
    }

    /**
     * Process given Coupon in order to get their cumulative effects
     *
     * @param array $coupons CouponInterface to process
     *
     * @return float discount
     */
    protected function getEffect(array $coupons)
    {
        $discount = 0.00;
        /** @var CouponInterface $coupon */
        foreach ($coupons as $coupon) {
            $discount += $coupon->exec($this->facade);
        }

        return $discount;
    }

    /**
     * Add an available CouponManager (Services)
     *
     * @param CouponInterface $coupon CouponManager
     */
    public function addAvailableCoupon(CouponInterface $coupon)
    {
        $this->availableCoupons[] = $coupon;
    }

    /**
     * Get all available CouponManagers (Services)
     *
     * @return array
     */
    public function getAvailableCoupons()
    {
        return $this->availableCoupons;
    }

    /**
     * Add an available ConstraintManager (Services)
     *
     * @param ConditionInterface $condition ConditionInterface
     */
    public function addAvailableCondition(ConditionInterface $condition)
    {
        $this->availableConditions[] = $condition;
    }

    /**
     * Get all available ConstraintManagers (Services)
     *
     * @return array
     */
    public function getAvailableConditions()
    {
        return $this->availableConditions;
    }

    /**
     * Decrement this coupon quantity
     *
     * To call when a coupon is consumed
     *
     * @param \Thelia\Model\Coupon $coupon Coupon consumed
     *
     * @return int Usage left after decremental
     */
    public function decrementQuantity(Coupon $coupon)
    {
        $ret = -1;
        try {

        $usageLeft = $coupon->getMaxUsage();

        if ($usageLeft > 0) {
            $usageLeft--;
            $coupon->setMaxUsage($usageLeft);

            $coupon->save();
            $ret = $usageLeft;
        }

        } catch (\Exception $e) {
            $ret = false;
        }

        return $ret;
    }
}
