<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Action;

use Propel\Runtime\ServiceContainer\ServiceContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Condition\ConditionFactory;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Core\Event\Coupon\CouponConsumeEvent;
use Thelia\Core\Event\Coupon\CouponCreateOrUpdateEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Coupon\CouponFactory;
use Thelia\Coupon\CouponManager;
use Thelia\Condition\ConditionCollection;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Model\Coupon as CouponModel;
use Thelia\Model\CouponQuery;
use Thelia\Model\OrderCoupon;

/**
 * Process Coupon Events
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class Coupon extends BaseAction implements EventSubscriberInterface
{
    /**
     * @var \Thelia\Core\HttpFoundation\Request
     */
    protected $request;

    /** @var CouponFactory $couponFactory */
    protected $couponFactory;

    /** @var CouponManager $couponManager */
    protected $couponManager;

    /** @var ConditionInterface $noConditionRule */
    protected $noConditionRule;

    /** @var ConditionFactory $conditionFactory */
    protected $conditionFactory;

    public function __construct(Request $request,
        CouponFactory $couponFactory, CouponManager $couponManager,
        ConditionInterface $noConditionRule, ConditionFactory $conditionFactory)
    {
        $this->request = $request;
        $this->couponFactory = $couponFactory;
        $this->couponManager = $couponManager;
        $this->noConditionRule = $noConditionRule;
        $this->conditionFactory = $conditionFactory;
    }

     /**
     * Occurring when a Coupon is about to be created
     *
     * @param CouponCreateOrUpdateEvent $event Event creation or update Coupon
     */
    public function create(CouponCreateOrUpdateEvent $event)
    {
        $coupon = new CouponModel();

        $this->createOrUpdate($coupon, $event);
    }

    /**
     * Occurring when a Coupon is about to be updated
     *
     * @param CouponCreateOrUpdateEvent $event Event creation or update Coupon
     */
    public function update(CouponCreateOrUpdateEvent $event)
    {
        $coupon = $event->getCouponModel();

        $this->createOrUpdate($coupon, $event);
    }

    /**
     * Occurring when a Coupon condition is about to be updated
     *
     * @param CouponCreateOrUpdateEvent $event Event creation or update Coupon condition
     */
    public function updateCondition(CouponCreateOrUpdateEvent $event)
    {
        $modelCoupon = $event->getCouponModel();

        $this->createOrUpdateCondition($modelCoupon, $event);
    }

    /**
     * Occurring when a Coupon condition is about to be consumed
     *
     * @param CouponConsumeEvent $event Event consuming Coupon
     */
    public function consume(CouponConsumeEvent $event)
    {
        $totalDiscount = 0;
        $isValid = false;

        /** @var CouponInterface $coupon */
        $coupon = $this->couponFactory->buildCouponFromCode($event->getCode());

        if ($coupon) {
            $isValid = $coupon->isMatching();
            if ($isValid) {
                $consumedCoupons = $this->request->getSession()->getConsumedCoupons();

                if (!isset($consumedCoupons) || !$consumedCoupons) {
                    $consumedCoupons = array();
                }

                // Prevent accumulation of the same Coupon on a Checkout
                $consumedCoupons[$event->getCode()] = $event->getCode();

                $this->request->getSession()->setConsumedCoupons($consumedCoupons);

                $totalDiscount = $this->couponManager->getDiscount();

                $this->request
                    ->getSession()
                    ->getCart()
                    ->setDiscount($totalDiscount)
                    ->save();
                $this->request
                    ->getSession()
                    ->getOrder()
                    ->setDiscount($totalDiscount)
                    // ->save()
                ;
            }
        }

        $event->setIsValid($isValid);
        $event->setDiscount($totalDiscount);
    }

    /**
     * Call the Model and delegate the create or delete action
     * Feed the Event with the updated model
     *
     * @param CouponModel               $coupon Model to save
     * @param CouponCreateOrUpdateEvent $event  Event containing data
     */
    protected function createOrUpdate(CouponModel $coupon, CouponCreateOrUpdateEvent $event)
    {
        $coupon->setDispatcher($event->getDispatcher());

        // Set default condition if none found
        /** @var ConditionInterface $noConditionRule */
        $noConditionRule = $this->getContainer()->get('thelia.condition.match_for_everyone');
        /** @var ConditionFactory $conditionFactory */
        $conditionFactory = $this->getContainer()->get('thelia.condition.factory');
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
            $event->getLocale()
        );

        $event->setCouponModel($coupon);
    }

    /**
     * Call the Model and delegate the create or delete action
     * Feed the Event with the updated model
     *
     * @param CouponModel               $coupon Model to save
     * @param CouponCreateOrUpdateEvent $event  Event containing data
     */
    protected function createOrUpdateCondition(CouponModel $coupon, CouponCreateOrUpdateEvent $event)
    {
        $coupon->setDispatcher($event->getDispatcher());

        /** @var ConditionFactory $conditionFactory */
        $conditionFactory = $this->getContainer()->get('thelia.condition.factory');

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
        if ($this->couponManager->isCouponRemovingPostage()) {
            $order = $event->getOrder();

            $order->setPostage(0);

            $event->setOrder($order);

            $event->stopPropagation();
        }
    }

    /**
     * @param \Thelia\Core\Event\Order\OrderEvent $event
     */
    public function afterOrder(OrderEvent $event)
    {
        $consumedCoupons = $this->request->getSession()->getConsumedCoupons();

        if (is_array($consumedCoupons)) {
            foreach ($consumedCoupons as $couponCode) {
                $couponQuery = CouponQuery::create();
                $couponModel = $couponQuery->findOneByCode($couponCode);
                $couponModel->setLocale($this->request->getSession()->getLang()->getLocale());

                /* decrease coupon quantity */
                $this->couponManager->decrementQuantity($couponModel);

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
                ;
                $orderCoupon->save();
            }
        }

        $this->request->getSession()->setConsumedCoupons(array());
    }

    /**
     * Returns an array of event names this subscriber listens to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::COUPON_CREATE => array("create", 128),
            TheliaEvents::COUPON_UPDATE => array("update", 128),
            TheliaEvents::COUPON_CONSUME => array("consume", 128),
            TheliaEvents::COUPON_CONDITION_UPDATE => array("updateCondition", 128),
            TheliaEvents::ORDER_SET_POSTAGE => array("testFreePostage", 256),
            TheliaEvents::ORDER_BEFORE_PAYMENT => array("afterOrder", 128),
        );
    }
}
