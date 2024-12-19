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

namespace Thelia\Action;

use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Condition\Implementation\MatchForEveryone;
use Thelia\Core\Event\Coupon\CouponConsumeEvent;
use Thelia\Core\Event\Coupon\CouponCreateOrUpdateEvent;
use Thelia\Core\Event\Coupon\CouponDeleteEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Coupon\CouponFactory;
use Thelia\Coupon\CouponManager;
use Thelia\Coupon\Type\CouponInterface;
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
    /** @var RequestStack */
    protected $requestStack;

    /** @var CouponFactory */
    protected $couponFactory;

    /** @var CouponManager */
    protected $couponManager;

    /** @var ConditionInterface */
    protected $noConditionRule;

    /** @var ConditionFactory */
    protected $conditionFactory;

    public function __construct(
        RequestStack $requestStack,
        CouponFactory $couponFactory,
        CouponManager $couponManager,
        MatchForEveryone $noConditionRule,
        ConditionFactory $conditionFactory
    ) {
        $this->requestStack = $requestStack;
        $this->couponFactory = $couponFactory;
        $this->couponManager = $couponManager;
        $this->noConditionRule = $noConditionRule;
        $this->conditionFactory = $conditionFactory;
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

        if (null === $coupon) {
            throw new \InvalidArgumentException(
                'The coupon should not be null'
            );
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

        $this->getSession()->setConsumedCoupons([]);

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
        $isValid = false;

        /** @var CouponInterface $coupon */
        $coupon = $this->couponFactory->buildCouponFromCode($event->getCode());

        if ($coupon) {
            $isValid = $coupon->isMatching();

            if ($isValid) {
                $this->couponManager->pushCouponInSession($event->getCode());
                $totalDiscount = $this->couponManager->getDiscount();

                $this->getSession()
                    ->getSessionCart($dispatcher)
                    ->setDiscount($totalDiscount)
                    ->save();

                $this->getSession()
                    ->getOrder()
                    ->setDiscount($totalDiscount)
                ;
            }
        }

        $event->setIsValid($isValid);
        $event->setDiscount($totalDiscount);
    }

    public function updateOrderDiscount(Event $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();
        if (!$session instanceof Session || !$session->isStarted()) {
            return;
        }

        $discount = $this->couponManager->getDiscount();

        $this->getSession()
            ->getSessionCart($dispatcher)
            ->setDiscount($discount)
            ->save();

        $this->getSession()
            ->getOrder()
            ->setDiscount($discount);
    }

    /**
     * Call the Model and delegate the create or delete action
     * Feed the Event with the updated model.
     *
     * @param CouponModel               $coupon Model to save
     * @param CouponCreateOrUpdateEvent $event  Event containing data
     */
    protected function createOrUpdate(CouponModel $coupon, CouponCreateOrUpdateEvent $event, EventDispatcherInterface $dispatcher): void
    {
        // Set default condition if none found
        /** @var ConditionInterface $noConditionRule */
        $noConditionRule = $this->noConditionRule;
        /** @var ConditionFactory $conditionFactory */
        $conditionFactory = $this->conditionFactory;
        $couponRuleCollection = new ConditionCollection();
        $couponRuleCollection[] = $noConditionRule;
        $defaultSerializedRule = $conditionFactory->serializeConditionCollection(
            $couponRuleCollection
        );

        $coupon->createOrUpdate(
            $event->getCode(),
            $event->getTitle(),
            $event->getEffects(),
            $event->getServiceId(),
            $event->isRemovingPostage(),
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
            $event->getStartDate()
        );

        $event->setCouponModel($coupon);
    }

    /**
     * Call the Model and delegate the create or delete action
     * Feed the Event with the updated model.
     *
     * @param CouponModel               $coupon Model to save
     * @param CouponCreateOrUpdateEvent $event  Event containing data
     */
    protected function createOrUpdateCondition(CouponModel $coupon, CouponCreateOrUpdateEvent $event, EventDispatcherInterface $dispatcher): void
    {
        /** @var ConditionFactory $conditionFactory */
        $conditionFactory = $this->conditionFactory;

        $coupon->createOrUpdateConditions(
            $conditionFactory->serializeConditionCollection($event->getConditions()),
            $event->getLocale()
        );

        $event->setCouponModel($coupon);
    }

    public function testFreePostage(OrderEvent $event): void
    {
        $order = $event->getOrder();

        if ($this->couponManager->isCouponRemovingPostage($order)) {
            $order->setPostage(0);

            $event->setOrder($order);

            $event->stopPropagation();
        }
    }

    /**
     * @throws \Exception if something goes wrong
     */
    public function afterOrder(OrderEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        /** @var CouponInterface[] $consumedCoupons */
        $consumedCoupons = $this->couponManager->getCouponsKept();

        if (\is_array($consumedCoupons) && \count($consumedCoupons) > 0) {
            $con = Propel::getWriteConnection(OrderCouponTableMap::DATABASE_NAME);
            $con->beginTransaction();

            try {
                foreach ($consumedCoupons as $couponCode) {
                    $couponQuery = CouponQuery::create();
                    $couponModel = $couponQuery->findOneByCode($couponCode->getCode());
                    $couponModel->setLocale($this->getSession()->getLang()->getLocale());

                    /* decrease coupon quantity */
                    $this->couponManager->decrementQuantity($couponModel, $event->getOrder()->getCustomerId());

                    /* memorize coupon */
                    $orderCoupon = new OrderCoupon();
                    $orderCoupon->setOrder($event->getOrder())
                        ->setCode($couponModel->getCode())
                        ->setType($couponModel->getType())
                        ->setAmount($couponCode->exec())

                        ->setTitle($couponModel->getTitle())
                        ->setShortDescription($couponModel->getShortDescription())
                        ->setDescription($couponModel->getDescription())

                        ->setStartDate($couponModel->getStartDate())
                        ->setExpirationDate($couponModel->getExpirationDate())
                        ->setIsCumulative($couponModel->getIsCumulative())
                        ->setIsRemovingPostage($couponModel->getIsRemovingPostage())
                        ->setIsAvailableOnSpecialOffers($couponModel->getIsAvailableOnSpecialOffers())
                        ->setSerializedConditions($couponModel->getSerializedConditions())
                        ->setPerCustomerUsageCount($couponModel->getPerCustomerUsageCount())
                    ;
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
            } catch (\Exception  $ex) {
                $con->rollBack();

                throw $ex;
            }
        }

        // Clear all coupons.
        $dispatcher->dispatch(new Event(), TheliaEvents::COUPON_CLEAR_ALL);
    }

    /**
     * Cancels order coupons usage when order is canceled or refunded,
     * or use canceled coupons again if the order is no longer canceled or refunded.
     *
     * @param string $eventName
     *
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function orderStatusChange(OrderEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        // The order has been canceled or refunded ?
        if ($event->getOrder()->isCancelled() || $event->getOrder()->isRefunded()) {
            // Cancel usage of all coupons for this order
            $usedCoupons = OrderCouponQuery::create()
                ->filterByUsageCanceled(false)
                ->findByOrderId($event->getOrder()->getId());

            $customerId = $event->getOrder()->getCustomerId();

            /** @var OrderCoupon $usedCoupon */
            foreach ($usedCoupons as $usedCoupon) {
                if (null !== $couponModel = CouponQuery::create()->findOneByCode($usedCoupon->getCode())) {
                    // If the coupon still exists, restore one usage to the usage count.
                    $this->couponManager->incrementQuantity($couponModel, $customerId);
                }

                // Mark coupon usage as canceled in the OrderCoupon table
                $usedCoupon->setUsageCanceled(true)->save();
            }
        } else {
            // Mark canceled coupons for this order as used again
            $usedCoupons = OrderCouponQuery::create()
                ->filterByUsageCanceled(true)
                ->findByOrderId($event->getOrder()->getId());

            $customerId = $event->getOrder()->getCustomerId();

            /** @var OrderCoupon $usedCoupon */
            foreach ($usedCoupons as $usedCoupon) {
                if (null !== $couponModel = CouponQuery::create()->findOneByCode($usedCoupon->getCode())) {
                    // If the coupon still exists, mark the coupon as used
                    $this->couponManager->decrementQuantity($couponModel, $customerId);
                }

                // The coupon is no longer canceled
                $usedCoupon->setUsageCanceled(false)->save();
            }
        }
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
            TheliaEvents::ORDER_SET_POSTAGE => ['testFreePostage', 132],
            TheliaEvents::ORDER_BEFORE_PAYMENT => ['afterOrder', 128],
            TheliaEvents::ORDER_UPDATE_STATUS => ['orderStatusChange', 10],
            TheliaEvents::CART_ADDITEM => ['updateOrderDiscount', 10],
            TheliaEvents::CART_UPDATEITEM => ['updateOrderDiscount', 10],
            TheliaEvents::CART_DELETEITEM => ['updateOrderDiscount', 10],
            TheliaEvents::CUSTOMER_LOGIN => ['updateOrderDiscount', 10],
            AddressEvent::POST_UPDATE => ['updateOrderDiscount', 10],
        ];
    }

    /**
     * Returns the session from the current request.
     *
     * @return \Thelia\Core\HttpFoundation\Session\Session
     */
    protected function getSession()
    {
        return $this->requestStack->getCurrentRequest()->getSession();
    }
}
