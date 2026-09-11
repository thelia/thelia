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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Condition\Exception\UnmatchableConditionException;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Promotion\Coupon\CouponFactory;
use Thelia\Domain\Promotion\Coupon\Exception\CouponExpiredException;
use Thelia\Domain\Promotion\Coupon\Exception\CouponNoUsageLeftException;
use Thelia\Domain\Promotion\Coupon\Exception\InactiveCouponException;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;
use Thelia\Model\Coupon;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponCustomerCount;
use Thelia\Model\CouponCustomerCountQuery;
use Thelia\Model\CouponModule;
use Thelia\Model\CouponQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\CouponTableMap;

/**
 * Manage how Coupons could interact with a Checkout.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class CouponManager implements ResetInterface
{
    /** @var array Available Coupons (Services) */
    protected array $availableCoupons = [];

    /** @var array Available Conditions (Services) */
    protected $availableConditions = [];

    /** @var CouponInterface[]|null Coupons built for the current request, invalidated on every cart mutation */
    private ?array $currentCoupons = null;

    public function __construct(
        protected FacadeInterface $facade,
        protected CouponFactory $couponFactory,
        protected RequestStack $requestStack,
    ) {
    }

    /**
     * Get Discount for the given Coupons.
     *
     * @return float checkout discount
     */
    public function getDiscount(): float
    {
        $discount = 0.00;

        $coupons = $this->getCurrentCoupons();

        if ([] !== $coupons) {
            $couponsKept = $this->sortCoupons($coupons);

            $discount = $this->getEffect($couponsKept);

            // Just In Case test. The facade total leaves the offered lines out (they
            // must not feed the conditions), but they ARE payable lines the discount
            // offsets: the cap is the taxed total of everything the cart holds.
            $checkoutTotalPrice = $this->facade->getCartTotalTaxPrice() + $this->offeredLinesTaxedTotal();

            if ($discount >= $checkoutTotalPrice) {
                $discount = $checkoutTotalPrice;
            }
        }

        return round($discount, 2);
    }

    /**
     * Return all coupons applying to the checkout: the ones typed in during the
     * session, plus the active automatic promotions, one instance per coupon row.
     *
     * The result is memoised for the request: getDiscount(), getCouponsKept() and
     * isCouponRemovingPostage() all go through here several times per evaluation
     * cycle. Any event mutating the cart must call invalidateCurrentCoupons() first.
     *
     * @return array Array of CouponInterface
     */
    public function getCurrentCoupons(): array
    {
        if (null !== $this->currentCoupons) {
            return $this->currentCoupons;
        }

        $sessionCoupons = $this->getSessionCoupons();

        return $this->currentCoupons = array_merge(
            $sessionCoupons,
            $this->getAutomaticCoupons($sessionCoupons),
        );
    }

    public function invalidateCurrentCoupons(): void
    {
        $this->currentCoupons = null;
    }

    public function reset(): void
    {
        $this->invalidateCurrentCoupons();
    }

    /**
     * The coupons the customer typed in during the session.
     *
     * @return CouponInterface[]
     */
    private function getSessionCoupons(): array
    {
        $session = $this->getSession();

        if (!$session instanceof Session) {
            // No session on this request (CLI, cold error page): no code was typed in.
            return [];
        }

        $couponCodes = $session->getConsumedCoupons();

        if (null === $couponCodes) {
            return [];
        }

        $coupons = [];
        $codesToRemove = [];

        foreach ($couponCodes as $couponCode) {
            // Only valid coupons are returned
            try {
                if (false !== $couponInterface = $this->couponFactory->buildCouponFromCode($couponCode)) {
                    $coupons[] = $couponInterface;
                }
            } catch (CouponExpiredException|InactiveCouponException|CouponNoUsageLeftException $ex) {
                // The coupon can no longer become valid again in this session: remove it from the cart
                // instead of leaving it there silently ignored on every calculation.
                $codesToRemove[] = $couponCode;

                Tlog::getInstance()->warning(
                    \sprintf('Coupon %s removed from cart, exception occurred: %s', $couponCode, $ex->getMessage()),
                );
            } catch (\Exception $ex) {
                // Just ignore the coupon and log the problem, just in case someone realize it.
                Tlog::getInstance()->warning(
                    \sprintf('Coupon %s ignored, exception occurred: %s', $couponCode, $ex->getMessage()),
                );
            }
        }

        if ([] !== $codesToRemove) {
            $session->setConsumedCoupons(array_values(array_diff($couponCodes, $codesToRemove)));
        }

        return $coupons;
    }

    /**
     * The enabled automatic promotions inside their date window, built one instance
     * per coupon row: a promotion already applied through its code is not returned twice.
     *
     * A per-customer usage limit needs a customer to be counted against: with no
     * customer signed in, such a promotion is silently ignored, never blocking.
     *
     * @param CouponInterface[] $sessionCoupons
     *
     * @return CouponInterface[]
     */
    private function getAutomaticCoupons(array $sessionCoupons): array
    {
        $sessionCodes = array_map(
            static fn (CouponInterface $coupon): string => $coupon->getCode(),
            $sessionCoupons,
        );

        $now = new \DateTime();

        // The same rules buildCouponFromCode() applies: enabled, started (or no start
        // date), not expired.
        $models = CouponQuery::create()
            ->filterByIsEnabled(true)
            ->filterByTriggerMode(Coupon::TRIGGER_MODE_AUTOMATIC)
            ->filterByExpirationDate($now, Criteria::GREATER_EQUAL)
            ->condition('start_unset', CouponTableMap::COL_START_DATE.' IS NULL')
            ->condition('start_reached', CouponTableMap::COL_START_DATE.' <= ?', $now)
            ->where(['start_unset', 'start_reached'], Criteria::LOGICAL_OR)
            ->find();

        $coupons = [];

        /** @var Coupon $model */
        foreach ($models as $model) {
            $code = $model->getCode();

            if (null !== $code && '' !== $code && \in_array($code, $sessionCodes, true)) {
                continue;
            }

            if (!$model->isUsageUnlimited()) {
                if (!($customer = $this->facade->getCustomer()) instanceof Customer) {
                    continue;
                }

                if ($model->getUsagesLeft($customer->getId()) <= 0) {
                    continue;
                }
            }

            try {
                $coupon = $this->couponFactory->buildCouponFromModel($model);
            } catch (\Exception $ex) {
                Tlog::getInstance()->warning(
                    \sprintf('Automatic promotion %d ignored, exception occurred: %s', $model->getId(), $ex->getMessage()),
                );

                continue;
            }

            if (0 === $coupon->getConditions()->count()) {
                continue;
            }

            $coupons[] = $coupon;
        }

        return $coupons;
    }

    /**
     * The taxed total of the offered lines of the cart, which the facade totals
     * deliberately leave out.
     */
    private function offeredLinesTaxedTotal(): float
    {
        $cart = $this->facade->getCart();

        if (null === $cart) {
            return 0.0;
        }

        $country = $this->facade->getDeliveryCountry();
        $total = 0.0;

        foreach ($cart->getCartItems() as $cartItem) {
            if (1 === (int) $cartItem->getIsOffered()) {
                $total += $cartItem->getTotalRealTaxedPrice($country);
            }
        }

        return $total;
    }

    private function getSession(): ?Session
    {
        $request = $this->requestStack->getMainRequest();

        if (null === $request || !$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();

        return $session instanceof Session ? $session : null;
    }

    public function pushCouponInSession($code): void
    {
        $this->facade->pushCouponInSession($code);

        $this->invalidateCurrentCoupons();
    }

    /**
     * Check if there is a Coupon removing Postage.
     */
    public function isCouponRemovingPostage(Cart $cart): bool
    {
        $coupons = $this->getCurrentCoupons();

        if ([] === $coupons) {
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
                    if (null === $deliveryAddress = $cart->getCartAddressRelatedByAddressDeliveryId()) {
                        continue;
                    }

                    $countryValid = false;

                    $deliveryCountryId = $deliveryAddress->getCountryId();

                    /** @var CouponCountry $couponCountry */
                    foreach ($couponCountries as $couponCountry) {
                        if ($deliveryCountryId === $couponCountry->getCountryId()) {
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

                    $shippingModuleId = $cart->getDeliveryModuleId();

                    /** @var CouponModule $couponModule */
                    foreach ($couponModules as $couponModule) {
                        if ($shippingModuleId === $couponModule->getModuleId()) {
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

    public function getCouponsKept(): array
    {
        return $this->sortCoupons($this->getCurrentCoupons());
    }

    /**
     * Sort Coupon to keep
     * Coupon not cumulative cancels previous.
     *
     * The coupons that do not apply to this cart are dropped FIRST: a coupon only
     * takes part in the cumulative rule when it actually matches, otherwise a
     * non-cumulative promotion whose conditions are not even met would evict the
     * coupons the customer is entitled to. The cumulative rule between the
     * matching coupons is then the historic one, unchanged (its overhaul is #158).
     *
     * @param array $coupons CouponInterface to process
     *
     * @return array Array of CouponInterface sorted
     */
    protected function sortCoupons(array $coupons): array
    {
        $matchingCoupons = [];

        /** @var CouponInterface $coupon */
        foreach ($coupons as $coupon) {
            if (!$coupon || $coupon->isExpired()) {
                continue;
            }

            try {
                if ($coupon->isMatching()) {
                    $matchingCoupons[] = $coupon;
                }
            } catch (UnmatchableConditionException) {
                // ignore unmatchable coupon
                continue;
            }
        }

        $couponsKept = [];

        /** @var CouponInterface $coupon */
        foreach ($matchingCoupons as $coupon) {
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

        return $couponsKept;
    }

    /**
     * Process given Coupon in order to get their cumulative effects.
     *
     * @param array $coupons CouponInterface to process
     *
     * @return float discount
     */
    protected function getEffect(array $coupons): float
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
    public function addAvailableCoupon(CouponInterface $coupon): void
    {
        $this->availableCoupons[] = $coupon;
    }

    /**
     * Get all available CouponManagers (Services).
     */
    public function getAvailableCoupons(): array
    {
        return $this->availableCoupons;
    }

    /**
     * Add an available ConstraintManager (Services).
     *
     * @param ConditionInterface $condition ConditionInterface
     */
    public function addAvailableCondition(ConditionInterface $condition): void
    {
        $this->availableConditions[] = $condition;
    }

    /**
     * Get all available ConstraintManagers (Services).
     */
    public function getAvailableConditions(): array
    {
        return $this->availableConditions;
    }

    /**
     * Clear all data kept by coupons.
     */
    public function clear(): void
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
     * @param Coupon   $coupon     Coupon consumed
     * @param int|null $customerId the ID of the ordering customer
     *
     * @return int Usage left after decremental
     */
    public function decrementQuantity(Coupon $coupon, ?int $customerId = null): int|float|bool
    {
        if ($coupon->isUsageUnlimited()) {
            return true;
        }

        try {
            $usageLeft = $coupon->getUsagesLeft($customerId);

            if ($usageLeft > 0) {
                // If the coupon usage is per user, add an entry to coupon customer usage count table
                if ($coupon->getPerCustomerUsageCount()) {
                    if (null === $customerId) {
                        throw new \LogicException('Customer should not be null at this time.');
                    }

                    $ccc = CouponCustomerCountQuery::create()
                        ->filterByCouponId($coupon->getId())
                        ->filterByCustomerId($customerId)
                        ->findOne();

                    if (null === $ccc) {
                        $ccc = new CouponCustomerCount();

                        $ccc
                            ->setCustomerId($customerId)
                            ->setCouponId($coupon->getId())
                            ->setCount(0);
                    }

                    $newCount = 1 + $ccc->getCount();

                    $ccc
                        ->setCount($newCount)
                        ->save();

                    return $usageLeft - $newCount;
                }

                $coupon->setMaxUsage(--$usageLeft);

                $coupon->save();

                return $usageLeft;
            }
        } catch (\Exception $exception) {
            // Just log the problem.
            Tlog::getInstance()->addError(\sprintf('Failed to decrement coupon %s: %s', $coupon->getCode(), $exception->getMessage()));
        }

        return false;
    }

    /**
     * Add a coupon usage, for the case the related order is canceled.
     */
    public function incrementQuantity(Coupon $coupon, ?int $customerId = null): int|float|bool
    {
        if ($coupon->isUsageUnlimited()) {
            return true;
        }

        try {
            $usageLeft = $coupon->getUsagesLeft($customerId);

            // If the coupon usage is per user, remove an entry from coupon customer usage count table
            if ($coupon->getPerCustomerUsageCount()) {
                if (null === $customerId) {
                    throw new \LogicException('Customer should not be null at this time.');
                }

                $ccc = CouponCustomerCountQuery::create()
                    ->filterByCouponId($coupon->getId())
                    ->filterByCustomerId($customerId)
                    ->findOne();

                if (null !== $ccc && $ccc->getCount() > 0) {
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
        } catch (\Exception $exception) {
            // Just log the problem.
            Tlog::getInstance()->addError(\sprintf('Failed to increment coupon %s: %s', $coupon->getCode(), $exception->getMessage()));
        }

        return false;
    }
}
