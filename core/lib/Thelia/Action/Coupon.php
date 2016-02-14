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

namespace Thelia\Action;

use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Core\Event\Coupon\CouponConsumeEvent;
use Thelia\Core\Event\Coupon\CouponCreateOrUpdateEvent;
use Thelia\Core\Event\Coupon\CouponDeleteEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Coupon\CouponFactory;
use Thelia\Coupon\CouponManager;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Model\Coupon as CouponModel;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponCountryQuery;
use Thelia\Model\CouponModule;
use Thelia\Model\CouponModuleQuery;
use Thelia\Model\CouponQuery;
use Thelia\Model\Map\OrderCouponTableMap;
use Thelia\Model\OrderCoupon;
use Thelia\Model\OrderCouponCountry;
use Thelia\Model\OrderCouponModule;

/**
 * Process Coupon Events
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>, Franck Allimant <franck@cqfdev.fr>
 *
 */
class Coupon extends BaseAction implements EventSubscriberInterface
{
    /** @var RequestStack */
    protected $requestStack;

    /** @var CouponFactory $couponFactory */
    protected $couponFactory;

    /** @var CouponManager $couponManager */
    protected $couponManager;

    /** @var ConditionInterface $noConditionRule */
    protected $noConditionRule;

    /** @var ConditionFactory $conditionFactory */
    protected $conditionFactory;

    public function __construct(
        RequestStack $requestStack,
        CouponFactory $couponFactory,
        CouponManager $couponManager,
        ConditionInterface $noConditionRule,
        ConditionFactory $conditionFactory
    ) {
        $this->requestStack = $requestStack;
        $this->couponFactory = $couponFactory;
        $this->couponManager = $couponManager;
        $this->noConditionRule = $noConditionRule;
        $this->conditionFactory = $conditionFactory;
    }

     /**
     * Occurring when a Coupon is about to be created
     *
     * @param CouponCreateOrUpdateEvent $event Event creation or update Coupon
      * @param $eventName
      * @param EventDispatcherInterface $dispatcher
     */
    public function create(CouponCreateOrUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $coupon = new CouponModel();

        $this->createOrUpdate($coupon, $event, $dispatcher);
    }

    /**
     * Occurring when a Coupon is about to be updated
     *
     * @param CouponCreateOrUpdateEvent $event Event creation or update Coupon
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function update(CouponCreateOrUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $coupon = $event->getCouponModel();

        $this->createOrUpdate($coupon, $event, $dispatcher);
    }

    public function delete(CouponDeleteEvent $event)
    {
        $coupon = $event->getCoupon();

        if (null === $coupon) {
            throw new \InvalidArgumentException(
                sprintf(
                    "The coupon id '%d' doesn't exist",
                    $event->getCouponId()
                )
            );
        }

        $coupon->delete();

        $event->setCoupon(null);
    }

    /**
     * Occurring when a Coupon condition is about to be updated
     *
     * @param CouponCreateOrUpdateEvent $event Event creation or update Coupon condition
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function updateCondition(CouponCreateOrUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $modelCoupon = $event->getCouponModel();

        $this->createOrUpdateCondition($modelCoupon, $event, $dispatcher);
    }

    /**
     * Clear all coupons in session.
     *
     * @param Event $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function clearAllCoupons(Event $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        // Tell coupons to clear any data they may have stored
        $this->couponManager->clear();

        $this->getSession()->setConsumedCoupons(array());

        $this->updateOrderDiscount($event, $eventName, $dispatcher);
    }

    /**
     * Occurring when a Coupon condition is about to be consumed
     *
     * @param CouponConsumeEvent $event Event consuming Coupon
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function consume(CouponConsumeEvent $event, $eventName, EventDispatcherInterface $dispatcher)
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

    public function updateOrderDiscount(Event $event, $eventName, EventDispatcherInterface $dispatcher)
    {
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
     * Feed the Event with the updated model
     *
     * @param CouponModel               $coupon Model to save
     * @param CouponCreateOrUpdateEvent $event  Event containing data
     * @param EventDispatcherInterface $dispatcher
     */
    protected function createOrUpdate(CouponModel $coupon, CouponCreateOrUpdateEvent $event, EventDispatcherInterface $dispatcher)
    {
        $coupon->setDispatcher($dispatcher);

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
            $event->getPerCustomerUsageCount()
        );

        $event->setCouponModel($coupon);
    }

    /**
     * Call the Model and delegate the create or delete action
     * Feed the Event with the updated model
     *
     * @param CouponModel               $coupon Model to save
     * @param CouponCreateOrUpdateEvent $event  Event containing data
     * @param EventDispatcherInterface $dispatcher
     */
    protected function createOrUpdateCondition(CouponModel $coupon, CouponCreateOrUpdateEvent $event, EventDispatcherInterface $dispatcher)
    {
        $coupon->setDispatcher($dispatcher);

        /** @var ConditionFactory $conditionFactory */
        $conditionFactory = $this->conditionFactory;

        $coupon->createOrUpdateConditions(
            $conditionFactory->serializeConditionCollection($event->getConditions()),
            $event->getLocale()
        );

        $event->setCouponModel($coupon);
    }

    /**
     * @param \Thelia\Core\Event\Order\OrderEvent $event
     */
    public function testFreePostage(OrderEvent $event)
    {
        $order = $event->getOrder();

        if ($this->couponManager->isCouponRemovingPostage($order)) {
            $order->setPostage(0);

            $event->setOrder($order);

            $event->stopPropagation();
        }
    }

    /**
     * @param \Thelia\Core\Event\Order\OrderEvent $event
     *
     * @throws \Exception if something goes wrong.
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function afterOrder(OrderEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        /** @var CouponInterface[] $consumedCoupons */
        $consumedCoupons = $this->couponManager->getCouponsKept();

        if (is_array($consumedCoupons) && count($consumedCoupons) > 0) {
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
                        ->setAmount($couponModel->getAmount())

                        ->setTitle($couponModel->getTitle())
                        ->setShortDescription($couponModel->getShortDescription())
                        ->setDescription($couponModel->getDescription())

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
                        ;
                    }

                    $couponModules = CouponModuleQuery::create()->filterByCouponId($couponModel->getId())->find();

                    /** @var CouponModule $couponModule */
                    foreach ($couponModules as $couponModule) {
                        $ocm = new OrderCouponModule();

                        $ocm
                            ->setCouponId($orderCoupon->getId())
                            ->setModuleId($couponModule->getModuleId())
                            ->save();
                        ;
                    }
                }

                $con->commit();
            } catch (\Exception  $ex) {
                $con->rollBack();

                throw($ex);
            }
        }

        // Clear all coupons.
        $dispatcher->dispatch(TheliaEvents::COUPON_CLEAR_ALL);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::COUPON_CREATE => array("create", 128),
            TheliaEvents::COUPON_UPDATE => array("update", 128),
            TheliaEvents::COUPON_DELETE => array("delete", 128),
            TheliaEvents::COUPON_CONSUME => array("consume", 128),
            TheliaEvents::COUPON_CLEAR_ALL => array("clearAllCoupons", 128),
            TheliaEvents::COUPON_CONDITION_UPDATE => array("updateCondition", 128),
            TheliaEvents::ORDER_SET_POSTAGE => array("testFreePostage", 132),
            TheliaEvents::ORDER_BEFORE_PAYMENT => array("afterOrder", 128),
            TheliaEvents::CART_ADDITEM => array("updateOrderDiscount", 10),
            TheliaEvents::CART_UPDATEITEM => array("updateOrderDiscount", 10),
            TheliaEvents::CART_DELETEITEM => array("updateOrderDiscount", 10),
        );
    }

    /**
     * Returns the session from the current request
     *
     * @return \Thelia\Core\HttpFoundation\Session\Session
     */
    protected function getSession()
    {
        return $this->requestStack->getCurrentRequest()->getSession();
    }
}
