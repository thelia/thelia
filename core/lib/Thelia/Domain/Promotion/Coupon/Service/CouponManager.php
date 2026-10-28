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
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Promotion\Coupon\CouponFactory;
use Thelia\Domain\Promotion\Coupon\Exception\CouponExpiredException;
use Thelia\Domain\Promotion\Coupon\Exception\CouponNoUsageLeftException;
use Thelia\Domain\Promotion\Coupon\Exception\InactiveCouponException;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Promotion\Coupon\Selection\PromotionSelection;
use Thelia\Domain\Promotion\Coupon\Type\CouponAbstract;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Domain\Promotion\Coupon\Type\FreeProduct;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;
use Thelia\Model\Coupon;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponCustomerCountQuery;
use Thelia\Model\CouponExclusionQuery;
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

    /** The selection made on $currentCoupons, valid while the state the conditions read is the one of $selectionFingerprint */
    private ?PromotionSelection $selection = null;

    private ?string $selectionFingerprint = null;

    private PromotionSelector $promotionSelector;

    public function __construct(
        protected FacadeInterface $facade,
        protected CouponFactory $couponFactory,
        protected RequestStack $requestStack,
        ?PromotionSelector $promotionSelector = null,
    ) {
        $this->promotionSelector = $promotionSelector ?? new PromotionSelector();
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
            $discount = $this->getEffect($this->getSelection()->kept);

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

    /**
     * The discount the automatic promotions give a cart, priced from the database alone: no
     * session is read nor started. For a caller that has none (the front API is stateless) and
     * has to bring the discount of a cart in line with its lines.
     *
     * What lives in a session is out of reach: the codes a buyer typed in, and the promotions
     * that keep their gifts in it (free product). They count for nothing here, so the caller
     * only uses this for a cart no session stands behind.
     */
    public function discountOfCartWithoutSession(Cart $cart, ?Customer $buyer): float
    {
        return CartDiscountScope::within($cart, $buyer, function (): float {
            $this->invalidateCurrentCoupons();

            try {
                return $this->getDiscount();
            } finally {
                // Built for this cart: not for the next one this request asks about.
                $this->invalidateCurrentCoupons();
            }
        });
    }

    public function invalidateCurrentCoupons(): void
    {
        $this->currentCoupons = null;
        $this->selection = null;
        $this->selectionFingerprint = null;
    }

    /**
     * Which promotions apply to the cart, and which ones another promotion pushed aside.
     *
     * Memoised: it is rebuilt when the coupons are invalidated, or when the cart, the
     * delivery or the customer the conditions read changed since it was made.
     */
    public function getSelection(): PromotionSelection
    {
        $coupons = $this->getCurrentCoupons();

        if ([] === $coupons) {
            return new PromotionSelection();
        }

        $fingerprint = $this->fingerprintState();

        if ($this->selection instanceof PromotionSelection && $this->selectionFingerprint === $fingerprint) {
            return $this->selection;
        }

        $this->selection = $this->selectCoupons($coupons);
        $this->selectionFingerprint = $fingerprint;

        return $this->selection;
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
        // Priced outside of a session: no code was typed in that this request could read.
        $session = null === CartDiscountScope::cart() ? $this->getSession() : null;

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

                if (!$this->couponFactory->hasAUsageLeftForANewOrder($model, $customer)) {
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

            // Keeps its gift in the session: it cannot be priced without one.
            if ($coupon instanceof FreeProduct && null !== CartDiscountScope::cart()) {
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
                $total += $cartItem->getSummedTotalRealTaxedPrice($country);
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
        if ([] === $this->getCurrentCoupons()) {
            return false;
        }

        /** @var CouponInterface $coupon */
        foreach ($this->getSelection()->kept as $coupon) {
            if ($coupon->isRemovingPostage()) {
                // Check if delivery country is on the list of countries for which delivery is free
                // If the list is empty, the shipping is free for all countries.
                $couponCountries = $coupon->getFreeShippingForCountries();

                if ([] !== $couponCountries) {
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

                if ([] !== $couponModules) {
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
        return $this->getSelection()->kept;
    }

    /**
     * Sort Coupon to keep, see PromotionSelector for the rule.
     *
     * @param array $coupons CouponInterface to process
     *
     * @return array Array of CouponInterface sorted
     */
    protected function sortCoupons(array $coupons): array
    {
        return $this->selectCoupons($coupons)->kept;
    }

    private function selectCoupons(array $coupons): PromotionSelection
    {
        $candidateIds = [];

        foreach ($coupons as $coupon) {
            if ($coupon instanceof CouponAbstract && null !== $coupon->getCouponModelId()) {
                $candidateIds[] = $coupon->getCouponModelId();
            }
        }

        $exclusions = \count($candidateIds) > 1
            ? CouponExclusionQuery::create()->excludedCouponIdsAmong($candidateIds)
            : [];

        return $this->promotionSelector->select($coupons, $exclusions);
    }

    /**
     * A cheap digest of everything the conditions of a promotion read: the cart (its lines,
     * quantities and prices), the delivery address and module, the currency, the countries, the customer.
     *
     * Without a request (CLI) there is no cart to read: the digest is then constant and the
     * selection lives as long as the coupons do.
     */
    private function fingerprintState(): string
    {
        try {
            $cart = $this->facade->getCart();
            $customer = $this->facade->getCustomer();
            $defaultAddress = $customer?->getDefaultAddress();
            $parts = [
                $cart?->getId(),
                $cart?->getAddressDeliveryId(),
                $cart?->getCartAddressRelatedByAddressDeliveryId()?->getCountryId(),
                $cart?->getDeliveryModuleId(),
                $customer?->getId(),
                // The currency the amount conditions read, the country the delivery ones read (the tax
                // engine's: picked address, else default address, else the visitor's), and the default
                // address the billing ones read.
                $this->facade->getCheckoutCurrency(),
                $this->facade->getDeliveryCountry()->getId(),
                $defaultAddress?->getId(),
                $defaultAddress?->getCountryId(),
                $this->facade->getCartTotalPrice(),
            ];

            foreach ($cart?->getCartItems() ?? [] as $cartItem) {
                $parts[] = [
                    $cartItem->getId(),
                    $cartItem->getQuantity(),
                    $cartItem->getPrice(),
                    $cartItem->getPromoPrice(),
                    $cartItem->getIsOffered(),
                ];
            }

            return md5(serialize($parts));
        } catch (\LogicException) {
            return '';
        }
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
     * Takes one usage off this coupon: off the coupon itself, or off what the
     * customer has left when the coupon is limited per customer.
     *
     * Nothing is taken when no usage is left, and the check and the write are a
     * single statement, so two orders paid at the same time never share the last
     * usage.
     *
     * @param Coupon   $coupon     Coupon consumed
     * @param int|null $customerId the ID of the ordering customer
     *
     * @return int|bool the usages left once this one is taken, true for an unlimited coupon, false when no usage was taken
     */
    public function decrementQuantity(Coupon $coupon, ?int $customerId = null): int|float|bool
    {
        if ($coupon->isUsageUnlimited()) {
            return true;
        }

        try {
            if ($coupon->getPerCustomerUsageCount()) {
                if (null === $customerId) {
                    throw new \LogicException('Customer should not be null at this time.');
                }

                if (!CouponCustomerCountQuery::create()->takeOneUsage($coupon->getId(), $customerId)) {
                    return false;
                }

                return $coupon->getUsagesLeft($customerId);
            }

            if (!CouponQuery::create()->takeOneUsage($coupon->getId())) {
                return false;
            }

            return $this->refreshMaxUsage($coupon);
        } catch (\Exception $exception) {
            // Just log the problem.
            Tlog::getInstance()->addError(\sprintf('Failed to decrement coupon %s: %s', $coupon->getCode(), $exception->getMessage()));
        }

        return false;
    }

    /**
     * Gives one usage back to this coupon, for the case the related order is canceled.
     *
     * @return int|bool the usages left once this one is given back, true for an unlimited coupon, false when nothing was given back
     */
    public function incrementQuantity(Coupon $coupon, ?int $customerId = null): int|float|bool
    {
        if ($coupon->isUsageUnlimited()) {
            return true;
        }

        try {
            if ($coupon->getPerCustomerUsageCount()) {
                if (null === $customerId) {
                    throw new \LogicException('Customer should not be null at this time.');
                }

                if (!CouponCustomerCountQuery::create()->giveBackOneUsage($coupon->getId(), $customerId)) {
                    return false;
                }

                return $coupon->getUsagesLeft($customerId);
            }

            if (!CouponQuery::create()->giveBackOneUsage($coupon->getId())) {
                return false;
            }

            return $this->refreshMaxUsage($coupon);
        } catch (\Exception $exception) {
            // Just log the problem.
            Tlog::getInstance()->addError(\sprintf('Failed to increment coupon %s: %s', $coupon->getCode(), $exception->getMessage()));
        }

        return false;
    }

    /**
     * The coupon row was written by a single statement, behind Propel's back:
     * the object takes the stored value, without marking it as a change to save.
     */
    private function refreshMaxUsage(Coupon $coupon): int
    {
        $maxUsage = CouponQuery::create()->findMaxUsage($coupon->getId()) ?? $coupon->getMaxUsage();

        $coupon->setMaxUsage($maxUsage);
        $coupon->resetModified(CouponTableMap::COL_MAX_USAGE);

        return $maxUsage;
    }
}
