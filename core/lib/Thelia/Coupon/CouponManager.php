<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace Thelia\Coupon;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Condition\ConditionInterface;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Model\Coupon;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
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

    /** @var array CouponInterface to process*/
    protected $coupons = array();

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
        $this->coupons = $this->facade->getCurrentCoupons();
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

        if (count($this->coupons) > 0) {
            $couponsKept = $this->sortCoupons($this->coupons);

            $isRemovingPostage = $this->isCouponRemovingPostage($couponsKept);

            $discount = $this->getEffect($couponsKept);

            if ($isRemovingPostage) {
                $postage = $this->facade->getCheckoutPostagePrice();
                $discount += $postage;
            }

            // Just In Case test
            $checkoutTotalPrice = $this->facade->getCartTotalPrice();
            if ($discount >= $checkoutTotalPrice) {
                $discount = $checkoutTotalPrice;
            }
        }

        return $discount;
    }

    /**
     * Check if there is a Coupon removing Postage
     *
     * @param array $couponsKept Array of CouponInterface sorted
     *
     * @return bool
     */
    protected function isCouponRemovingPostage(array $couponsKept)
    {
        $isRemovingPostage = false;

        /** @var CouponInterface $coupon */
        foreach ($couponsKept as $coupon) {
            if ($coupon->isRemovingPostage()) {
                $isRemovingPostage = true;
            }
        }

        return $isRemovingPostage;
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
            if (!$coupon->isExpired()) {
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
     * @return bool
     */
    public function decrementeQuantity(Coupon $coupon)
    {
        $ret = true;
        try {

        $oldMaxUsage = $coupon->getMaxUsage();

        if ($oldMaxUsage > 0) {
            $oldMaxUsage--;
            $coupon->setMaxUsage($$oldMaxUsage);

            $coupon->save();
        }

        } catch(\Exception $e) {
            $ret = false;
        }

        return $ret;
    }
}