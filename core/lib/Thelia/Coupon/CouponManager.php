<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Coupon;

use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Exception\UnmatchableConditionException;
use Thelia\Log\Tlog;
use Thelia\Model\AddressQuery;
use Thelia\Model\Coupon;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponCustomerCount;
use Thelia\Model\CouponCustomerCountQuery;
use Thelia\Model\CouponModule;
use Thelia\Model\Order;

/**
 * Manage how Coupons could interact with a Checkout.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class CouponManager
{
    /** @var FacadeInterface Provides necessary value from Thelia */
    protected $facade;

    /** @var array Available Coupons (Services) */
    protected $availableCoupons = [];

    /** @var array Available Conditions (Services) */
    protected $availableConditions = [];

    /**
     * @var Request
     */
    private $request;
    /**
     * @var CouponFactory
     */
    private $couponFactory;

    /**S
     * Constructor
     *
     * @param FacadeInterface $facade Service container
     */
    public function __construct(FacadeInterface $facade)
    {
        $this->facade = $facade;
    }

    /**
     * Get Discount for the given Coupons.
     *
     * @api
     *
     * @return float checkout discount
     */
    public function getDiscount()
    {
        $discount = 0.00;

        $coupons = $this->getCurrentCoupons();

        if (\count($coupons) > 0) {
            $couponsKept = $this->sortCoupons($coupons);

            $discount = $this->getEffect($couponsKept);

            // Just In Case test
            $checkoutTotalPrice = $this->facade->getCartTotalTaxPrice();

            if ($discount >= $checkoutTotalPrice) {
                $discount = $checkoutTotalPrice;
            }
        }

        return round($discount, 2);
    }

    /**
     * Return all Coupon given during the Checkout.
     *
     * @return array Array of CouponInterface
     */
    public function getCurrentCoupons()
    {
        $couponCodes = $this->facade->getRequest()->getSession()->getConsumedCoupons();

        if (null === $couponCodes) {
            return [];
        }

        $coupons = [];

        foreach ($couponCodes as $couponCode) {
            // Only valid coupons are returned
            try {
                if (false !== $couponInterface = $this->couponFactory->buildCouponFromCode($couponCode)) {
                    $coupons[] = $couponInterface;
                }
            } catch (\Exception $ex) {
                // Just ignore the coupon and log the problem, just in case someone realize it.
                Tlog::getInstance()->warning(
                    sprintf('Coupon %s ignored, exception occurred: %s', $couponCode, $ex->getMessage())
                );
            }
        }

        return $coupons;
    }

    /**
     * @param $code
     *
     * @return mixed|void
     */
    public function pushCouponInSession($code)
    {
        $this->facade->pushCouponInSession($code);
    }

    /**
     * Check if there is a Coupon removing Postage.
     *
     * @param Order $order the order for which we have to check if postage is free
     *
     * @return bool
     */
    public function isCouponRemovingPostage(Order $order)
    {
        $coupons = $this->getCurrentCoupons();

        if (\count($coupons) == 0) {
            return false;
        }

        $couponsKept = $this->sortCoupons($coupons);

        /** @var CouponInterface $coupon */
        foreach ($couponsKept as $coupon) {
            if ($coupon->isRemovingPostage()) {
                // Check if delivery country is on the list of countries for which delivery is free
                // If the list is empty, the shipping is free for all countries.
                $couponCountries = $coupon->getFreeShippingForCountries();

                if (!$couponCountries->isEmpty()) {
                    if (null === $deliveryAddress = AddressQuery::create()->findPk($order->getChoosenDeliveryAddress())) {
                        continue;
                    }

                    $countryValid = false;

                    $deliveryCountryId = $deliveryAddress->getCountryId();

                    /** @var CouponCountry $couponCountry */
                    foreach ($couponCountries as $couponCountry) {
                        if ($deliveryCountryId == $couponCountry->getCountryId()) {
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

                    $shippingModuleId = $order->getDeliveryModuleId();

                    /** @var CouponModule $couponModule */
                    foreach ($couponModules as $couponModule) {
                        if ($shippingModuleId == $couponModule->getModuleId()) {
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
        }

        return false;
    }

    /**
     * @return array
     */
    public function getCouponsKept()
    {
        return $this->sortCoupons($this->getCurrentCoupons());
    }

    /**
     * Sort Coupon to keep
     * Coupon not cumulative cancels previous.
     *
     * @param array $coupons CouponInterface to process
     *
     * @return array Array of CouponInterface sorted
     */
    protected function sortCoupons(array $coupons)
    {
        $couponsKept = [];

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
                            $couponsKept = [$coupon];
                        }
                    } else {
                        // Reset Coupons, add last
                        $couponsKept = [$coupon];
                    }
                } else {
                    // Reset Coupons, add last
                    $couponsKept = [$coupon];
                }
            }
        }

        $coupons = $couponsKept;
        $couponsKept = [];

        /** @var CouponInterface $coupon */
        foreach ($coupons as $coupon) {
            try {
                if ($coupon->isMatching()) {
                    $couponsKept[] = $coupon;
                }
            } catch (UnmatchableConditionException $e) {
                // ignore unmatchable coupon
                continue;
            }
        }

        return $couponsKept;
    }

    /**
     * Process given Coupon in order to get their cumulative effects.
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
            $discount += $coupon->exec();
        }

        return $discount;
    }

    /**
     * Add an available CouponManager (Services).
     *
     * @param CouponInterface $coupon CouponManager
     */
    public function addAvailableCoupon(CouponInterface $coupon)
    {
        $this->availableCoupons[] = $coupon;
    }

    /**
     * Get all available CouponManagers (Services).
     *
     * @return array
     */
    public function getAvailableCoupons()
    {
        return $this->availableCoupons;
    }

    /**
     * Add an available ConstraintManager (Services).
     *
     * @param ConditionInterface $condition ConditionInterface
     */
    public function addAvailableCondition(ConditionInterface $condition)
    {
        $this->availableConditions[] = $condition;
    }

    /**
     * Get all available ConstraintManagers (Services).
     *
     * @return array
     */
    public function getAvailableConditions()
    {
        return $this->availableConditions;
    }

    /**
     * Clear all data kept by coupons.
     */
    public function clear()
    {
        $coupons = $this->getCurrentCoupons();

        /** @var CouponInterface $coupon */
        foreach ($coupons as $coupon) {
            $coupon->clear();
        }
    }

    /**
     * Decrement this coupon quantity.
     *
     * To call when a coupon is consumed
     *
     * @param \Thelia\Model\Coupon $coupon     Coupon consumed
     * @param int|null             $customerId the ID of the ordering customer
     *
     * @return int Usage left after decremental
     */
    public function decrementQuantity(Coupon $coupon, $customerId = null)
    {
        if ($coupon->isUsageUnlimited()) {
            return true;
        }
        try {
            $usageLeft = $coupon->getUsagesLeft($customerId);

            if ($usageLeft > 0) {
                // If the coupon usage is per user, add an entry to coupon customer usage count table
                if ($coupon->getPerCustomerUsageCount()) {
                    if (null == $customerId) {
                        throw new \LogicException('Customer should not be null at this time.');
                    }

                    $ccc = CouponCustomerCountQuery::create()
                            ->filterByCouponId($coupon->getId())
                            ->filterByCustomerId($customerId)
                            ->findOne()
                        ;

                    if ($ccc === null) {
                        $ccc = new CouponCustomerCount();

                        $ccc
                                ->setCustomerId($customerId)
                                ->setCouponId($coupon->getId())
                                ->setCount(0);
                    }

                    $newCount = 1 + $ccc->getCount();

                    $ccc
                            ->setCount($newCount)
                            ->save()
                        ;

                    return $usageLeft - $newCount;
                }
                $coupon->setMaxUsage(--$usageLeft);

                $coupon->save();

                return $usageLeft;
            }
        } catch (\Exception $ex) {
            // Just log the problem.
            Tlog::getInstance()->addError(sprintf('Failed to decrement coupon %s: %s', $coupon->getCode(), $ex->getMessage()));
        }

        return false;
    }

    /**
     * Add a coupon usage, for the case the related order is canceled.
     *
     * @param int $customerId
     */
    public function incrementQuantity(Coupon $coupon, $customerId = null)
    {
        if ($coupon->isUsageUnlimited()) {
            return true;
        }
        try {
            $usageLeft = $coupon->getUsagesLeft($customerId);

            // If the coupon usage is per user, remove an entry from coupon customer usage count table
            if ($coupon->getPerCustomerUsageCount()) {
                if (null == $customerId) {
                    throw new \LogicException('Customer should not be null at this time.');
                }

                $ccc = CouponCustomerCountQuery::create()
                        ->filterByCouponId($coupon->getId())
                        ->filterByCustomerId($customerId)
                        ->findOne()
                    ;

                if ($ccc !== null && $ccc->getCount() > 0) {
                    $newCount = $ccc->getCount() - 1;

                    $ccc
                            ->setCount($newCount)
                            ->save();

                    return $usageLeft - $newCount;
                }
            } else {
                // Ad one usage to coupon
                $coupon->setMaxUsage(++$usageLeft);

                $coupon->save();

                return $usageLeft;
            }
        } catch (\Exception $ex) {
            // Just log the problem.
            Tlog::getInstance()->addError(sprintf('Failed to increment coupon %s: %s', $coupon->getCode(), $ex->getMessage()));
        }

        return false;
    }
}
