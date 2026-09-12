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

namespace Thelia\Action;

use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\MatchForEveryone;
use Thelia\Core\Event\Coupon\CouponConsumeEvent;
use Thelia\Core\Event\Coupon\CouponCreateOrUpdateEvent;
use Thelia\Core\Event\Coupon\CouponDeleteEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Promotion\Coupon\CouponFactory;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Domain\Promotion\Coupon\Service\OfferedCartLineService;
use Thelia\Domain\Promotion\Coupon\Type\CouponAbstract;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Coupon as CouponModel;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponCountryQuery;
use Thelia\Model\CouponModule;
use Thelia\Model\CouponModuleQuery;
use Thelia\Model\CouponQuery;
use Thelia\Model\Event\AddressEvent;
use Thelia\Model\Map\OrderCouponTableMap;
use Thelia\Model\OrderCoupon;
use Thelia\Model\OrderCouponCountry;
use Thelia\Model\OrderCouponModule;
use Thelia\Model\OrderCouponQuery;

/**
 * Process Coupon Events.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>, Franck Allimant <franck@cqfdev.fr>
 */
class Coupon extends BaseAction implements EventSubscriberInterface
{
    public function __construct(
        protected RequestStack $requestStack,
        protected CouponFactory $couponFactory,
        protected CouponManager $couponManager,
        protected MatchForEveryone $noConditionRule,
        protected ConditionFactory $conditionFactory,
        protected EventDispatcherInterface $dispatcher,
        protected OfferedCartLineService $offeredCartLineService,
    ) {
    }

    /**
     * Occurring when a Coupon is about to be created.
     *
     * @param CouponCreateOrUpdateEvent $event Event creation or update Coupon
     */
    public function create(CouponCreateOrUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $coupon = new CouponModel();

        $this->createOrUpdate($coupon, $event, $dispatcher);
    }

    /**
     * Occurring when a Coupon is about to be updated.
     *
     * @param CouponCreateOrUpdateEvent $event Event creation or update Coupon
     */
    public function update(CouponCreateOrUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $coupon = $event->getCouponModel();

        $this->createOrUpdate($coupon, $event, $dispatcher);
    }

    public function delete(CouponDeleteEvent $event): void
    {
        $coupon = $event->getCoupon();

        if (!$coupon instanceof CouponModel) {
            throw new \InvalidArgumentException('The coupon should not be null');
        }

        $coupon->delete();

        $event->setCoupon(null);
    }

    /**
     * Occurring when a Coupon condition is about to be updated.
     *
     * @param CouponCreateOrUpdateEvent $event Event creation or update Coupon condition
     */
    public function updateCondition(CouponCreateOrUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $modelCoupon = $event->getCouponModel();

        $this->createOrUpdateCondition($modelCoupon, $event, $dispatcher);
    }

    /**
     * Clear all coupons in session.
     */
    public function clearAllCoupons(Event $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        // Tell coupons to clear any data they may have stored
        $this->couponManager->clear();

        $this->getSession()?->setConsumedCoupons([]);

        $this->updateOrderDiscount($event, $eventName, $dispatcher);
    }

    /**
     * Occurring when a Coupon condition is about to be consumed.
     *
     * @param CouponConsumeEvent $event Event consuming Coupon
     */
    public function consume(CouponConsumeEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $totalDiscount = 0;

        $coupon = $this->couponFactory->buildCouponFromCode($event->getCode());

        $isValid = $coupon->isMatching();
        if ($isValid) {
            $this->couponManager->pushCouponInSession($event->getCode());
            $totalDiscount = $this->couponManager->getDiscount();

            $this->getSession()?->getSessionCart($dispatcher)
                ->setDiscount((string) $totalDiscount)
                ->save();

            $this->getSession()?->getOrder()
                ->setDiscount((string) $totalDiscount);
        }

        $event->setIsValid($isValid);
        $event->setDiscount($totalDiscount);
    }

    public function updateOrderDiscount(Event $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $session = $this->requestStack->getMainRequest()?->getSession();

        if (!$session instanceof Session || !$session->isStarted()) {
            return;
        }

        // The cart just changed: the coupons memoised for this request are stale.
        $this->couponManager->invalidateCurrentCoupons();

        $cart = $session->getSessionCart($dispatcher);

        if (null !== $cart) {
            // Put the offered lines in line with what the kept promotions ask for,
            // BEFORE pricing the discount on what the cart now holds.
            $this->offeredCartLineService->reconcile($this->couponManager->getCouponsKept(), $cart);
        }

        $discount = $this->couponManager->getDiscount();

        $session->getSessionCart($dispatcher)
            ->setDiscount((string) $discount)
            ->save();

        $session->getOrder()
            ->setDiscount((string) $discount);
    }

    /**
     * Last chance to price the promotions before the order is written.
     *
     * The buyer can sit on the payment page long after the last cart change, and
     * a promotion can stop applying in the meantime: disabled by the merchant,
     * expired, out of stock for the product it offers, or used up by someone else.
     * Without this pass the order is billed with a discount nothing explains, and
     * no order_coupon row is written for it.
     *
     * Only carts the coupon machinery has a say in are touched: a discount put on
     * a cart by something else is left where it is.
     */
    public function reconcileBeforeOrder(Event $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $session = $this->requestStack->getMainRequest()?->getSession();

        if (!$session instanceof Session || !$session->isStarted()) {
            return;
        }

        if (!$this->couponsCanExplainTheDiscount($session->getSessionCart($dispatcher))) {
            return;
        }

        $this->updateOrderDiscount($event, $eventName, $dispatcher);
    }

    /**
     * Whether the discount carried by this cart can be the work of a coupon: a code
     * the buyer typed, a line a promotion offered, or an automatic promotion the shop
     * declares — disabled ones included, since a promotion withdrawn since the last
     * cart change is exactly the case to catch.
     */
    private function couponsCanExplainTheDiscount(?Cart $cart): bool
    {
        if ([] !== ($this->getSession()?->getConsumedCoupons() ?? [])) {
            return true;
        }

        if (null !== $cart
            && null !== CartItemQuery::create()
                ->filterByCartId($cart->getId())
                ->filterByIsOffered(1)
                ->findOne()
        ) {
            return true;
        }

        return CouponQuery::create()
            ->filterByTriggerMode(CouponModel::TRIGGER_MODE_AUTOMATIC)
            ->count() > 0;
    }

    /**
     * Call the Model and delegate the create or delete action
     * Feed the Event with the updated model.
     *
     * @param CouponModel               $coupon Model to save
     * @param CouponCreateOrUpdateEvent $event  Event containing data
     *
     * @throws \Exception
     */
    protected function createOrUpdate(CouponModel $coupon, CouponCreateOrUpdateEvent $event, EventDispatcherInterface $dispatcher): void
    {
        // Set default condition if none found
        $noConditionRule = $this->noConditionRule;
        $conditionFactory = $this->conditionFactory;
        $couponRuleCollection = new ConditionCollection();
        $couponRuleCollection[] = $noConditionRule;
        $defaultSerializedRule = $conditionFactory->serializeConditionCollection(
            $couponRuleCollection,
        );

        // An automatic promotion applies on its own: it carries no code.
        $isAutomatic = CouponModel::TRIGGER_MODE_AUTOMATIC === $event->getTriggerMode();

        $coupon->createOrUpdate(
            $isAutomatic ? null : $event->getCode(),
            $event->getTitle(),
            $event->getEffects(),
            $event->getServiceId(),
            $event->isRemovingPostage() ?? false,
            $event->getShortDescription(),
            $event->getDescription(),
            $event->isEnabled(),
            $event->getExpirationDate(),
            $event->isAvailableOnSpecialOffers(),
            $event->isCumulative(),
            $event->getMaxUsage(),
            $defaultSerializedRule,
            $event->getLocale(),
            $event->getFreeShippingForCountries(),
            $event->getFreeShippingForMethods(),
            $event->getPerCustomerUsageCount(),
            $event->getStartDate(),
            $event->getTriggerMode(),
        );

        $event->setCouponModel($coupon);
    }

    /**
     * Call the Model and delegate the create or delete action
     * Feed the Event with the updated model.
     *
     * @param CouponModel               $coupon Model to save
     * @param CouponCreateOrUpdateEvent $event  Event containing data
     *
     * @throws \Exception
     */
    protected function createOrUpdateCondition(CouponModel $coupon, CouponCreateOrUpdateEvent $event, EventDispatcherInterface $dispatcher): void
    {
        $conditionFactory = $this->conditionFactory;

        $coupon->createOrUpdateConditions(
            $conditionFactory->serializeConditionCollection($event->getConditions()),
            $event->getLocale(),
        );

        $event->setCouponModel($coupon);
    }

    public function checkFreePostage(OrderEvent $event): void
    {
        /** @var Cart $cart */
        $cart = $this->requestStack->getMainRequest()?->getSession()->getSessionCart($this->dispatcher);

        if ($this->couponManager->isCouponRemovingPostage($cart)) {
            $cart->setPostage(null)
                ->setPostageTax(null)
                ->setPostageTaxRuleTitle(null)
                ->save();

            $event->stopPropagation();
        }
    }

    public function forceFreePostage(mixed $event): void
    {
        /** @var Cart $cart */
        $cart = $this->requestStack->getMainRequest()?->getSession()->getSessionCart($this->dispatcher);

        if ($this->couponManager->isCouponRemovingPostage($cart)) {
            $cart->setPostage(null)
                ->setPostageTax(null)
                ->setPostageTaxRuleTitle(null)
                ->save();

            $event->stopPropagation();
        }
    }

    /**
     * @throws \Exception if something goes wrong
     */
    public function afterOrder(OrderEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $consumedCoupons = $this->couponManager->getCouponsKept();

        if ([] === $consumedCoupons) {
            return;
        }
        $con = Propel::getWriteConnection(OrderCouponTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            foreach ($consumedCoupons as $consumedCoupon) {
                $couponModel = $this->resolveCouponModel($consumedCoupon);

                if (null === $couponModel) {
                    Tlog::getInstance()->warning(
                        \sprintf('Coupon "%s" could not be resolved when recording the order coupons', $consumedCoupon->getCode()),
                    );

                    continue;
                }

                $couponModel->setLocale($this->getSession()?->getLang()?->getLocale());

                /* memorize coupon. Its usage is not counted yet: this is done when the order is paid. */
                $orderCoupon = new OrderCoupon();
                $orderCoupon->setOrder($event->getOrder())
                    ->setUsageCanceled(1)
                    ->setCouponId($couponModel->getId())
                    ->setCode($couponModel->getCode())
                    ->setType($couponModel->getType())
                    ->setAmount((string) round($consumedCoupon->exec(), 2))
                    ->setTitle($couponModel->getTitle())
                    ->setShortDescription($couponModel->getShortDescription())
                    ->setDescription($couponModel->getDescription())
                    ->setStartDate($couponModel->getStartDate())
                    ->setExpirationDate($couponModel->getExpirationDate())
                    ->setIsCumulative($couponModel->getIsCumulative())
                    ->setIsRemovingPostage($couponModel->getIsRemovingPostage())
                    ->setIsAvailableOnSpecialOffers($couponModel->getIsAvailableOnSpecialOffers())
                    ->setSerializedConditions($couponModel->getSerializedConditions())
                    ->setSerializedEffects($couponModel->getSerializedEffects())
                    ->setPerCustomerUsageCount($couponModel->getPerCustomerUsageCount());
                $orderCoupon->save();

                // Copy order coupon free shipping data for countries and modules
                $couponCountries = CouponCountryQuery::create()->filterByCouponId($couponModel->getId())->find();

                /** @var CouponCountry $couponCountry */
                foreach ($couponCountries as $couponCountry) {
                    $occ = new OrderCouponCountry();

                    $occ
                        ->setCouponId($orderCoupon->getId())
                        ->setCountryId($couponCountry->getCountryId())
                        ->save();
                }

                $couponModules = CouponModuleQuery::create()->filterByCouponId($couponModel->getId())->find();

                /** @var CouponModule $couponModule */
                foreach ($couponModules as $couponModule) {
                    $ocm = new OrderCouponModule();

                    $ocm
                        ->setCouponId($orderCoupon->getId())
                        ->setModuleId($couponModule->getModuleId())
                        ->save();
                }
            }

            $con->commit();
        } catch (\Throwable $ex) {
            $con->rollBack();

            throw $ex;
        }

        // Clear all coupons.
        $dispatcher->dispatch(new Event(), TheliaEvents::COUPON_CLEAR_ALL);
    }

    /**
     * Counts the usage of the coupons of an order as soon as the order is paid, and gives it back to
     * the coupons when the order is no longer paid: canceled, refunded, or back to the "not paid" status.
     *
     * Any other status, a custom one for example, leaves the coupon usage count unchanged.
     *
     * @throws \Exception
     * @throws PropelException
     */
    public function orderStatusChange(OrderEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        $order = $event->getOrder();

        // The order is no longer paid ?
        if ($order->isNotPaid() || $order->isCancelled() || $order->isRefunded()) {
            // Cancel usage of all coupons for this order
            $usedCoupons = OrderCouponQuery::create()
                ->filterByUsageCanceled(false)
                ->findByOrderId($order->getId());

            $customerId = $order->getCustomerId();

            /** @var OrderCoupon $usedCoupon */
            foreach ($usedCoupons as $usedCoupon) {
                if (null !== $couponModel = $this->resolveOrderCouponModel($usedCoupon)) {
                    // If the coupon still exists, restore one usage to the usage count.
                    $this->couponManager->incrementQuantity($couponModel, $customerId);
                }

                // Mark coupon usage as canceled in the OrderCoupon table
                $usedCoupon->setUsageCanceled(1)->save();
            }
        } elseif ($order->isPaid(false)) {
            // Count the usage of the coupons which are not counted yet
            $usedCoupons = OrderCouponQuery::create()
                ->filterByUsageCanceled(true)
                ->findByOrderId($order->getId());

            $customerId = $order->getCustomerId();

            /** @var OrderCoupon $usedCoupon */
            foreach ($usedCoupons as $usedCoupon) {
                if (null !== $couponModel = $this->resolveOrderCouponModel($usedCoupon)) {
                    // If the coupon still exists, mark the coupon as used
                    $this->couponManager->decrementQuantity($couponModel, $customerId);
                }

                // The coupon is no longer canceled
                $usedCoupon->setUsageCanceled(0)->save();
            }
        }
    }

    /**
     * The coupon row a coupon built for the checkout came from: by id when the
     * instance carries one, by code otherwise. An automatic promotion has no code,
     * so the id is the only reliable link.
     */
    protected function resolveCouponModel(CouponInterface $coupon): ?CouponModel
    {
        if ($coupon instanceof CouponAbstract && null !== $coupon->getCouponModelId()) {
            return CouponQuery::create()->findPk($coupon->getCouponModelId());
        }

        if ('' !== $coupon->getCode()) {
            return CouponQuery::create()->findOneByCode($coupon->getCode());
        }

        return null;
    }

    /**
     * The coupon row an order coupon was recorded from: by id first, falling back
     * to the code for order coupons recorded before coupon_id existed.
     */
    protected function resolveOrderCouponModel(OrderCoupon $orderCoupon): ?CouponModel
    {
        if (null !== $orderCoupon->getCouponId()
            && null !== $couponModel = CouponQuery::create()->findPk($orderCoupon->getCouponId())) {
            return $couponModel;
        }

        $code = $orderCoupon->getCode();

        if (null !== $code && '' !== $code) {
            return CouponQuery::create()->findOneByCode($code);
        }

        return null;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::COUPON_CREATE => ['create', 128],
            TheliaEvents::COUPON_UPDATE => ['update', 128],
            TheliaEvents::COUPON_DELETE => ['delete', 128],
            TheliaEvents::COUPON_CONSUME => ['consume', 128],
            TheliaEvents::COUPON_CLEAR_ALL => ['clearAllCoupons', 128],
            TheliaEvents::COUPON_CONDITION_UPDATE => ['updateCondition', 128],
            TheliaEvents::ORDER_SET_POSTAGE => ['forceFreePostage', 132],
            TheliaEvents::CART_SET_POSTAGE => ['forceFreePostage', 132],
            TheliaEvents::ORDER_BEFORE_PAYMENT => ['afterOrder', 128],
            TheliaEvents::ORDER_UPDATE_STATUS => ['orderStatusChange', 10],
            TheliaEvents::CART_ADDITEM => ['updateOrderDiscount', 10],
            TheliaEvents::CART_UPDATEITEM => ['updateOrderDiscount', 10],
            TheliaEvents::CART_DELETEITEM => ['updateOrderDiscount', 10],
            TheliaEvents::CUSTOMER_LOGIN => ['updateOrderDiscount', 10],
            AddressEvent::POST_UPDATE => ['updateOrderDiscount', 10],
            // The promotions conditioned on the shipping choice (MatchDeliveryModules,
            // delivery country) must follow the checkout selections. Priority 10 runs
            // after Action\Cart (128) has written the selection on the cart.
            TheliaEvents::CART_SET_DELIVERY_MODULE => ['updateOrderDiscount', 10],
            TheliaEvents::CART_SET_DELIVERY_ADDRESS => ['updateOrderDiscount', 10],
            TheliaEvents::CART_SET_DELIVERY_ADDRESS_MANUAL => ['updateOrderDiscount', 10],
            // The buyer can sit on the payment page long after the last cart change,
            // and an automatic promotion can stop applying in the meantime: disabled
            // by the merchant, expired, or out of stock for its offered product.
            // 200 runs before Action\Order::create (128), so the order is built from a
            // cart whose discount and offered lines are the ones that still hold.
            TheliaEvents::ORDER_PAY => ['reconcileBeforeOrder', 200],
        ];
    }

    /**
     * Returns the session from the current request.
     */
    protected function getSession(): ?Session
    {
        /** @var Session $session */
        $session = $this->requestStack->getMainRequest()?->getSession();

        return $session;
    }
}
