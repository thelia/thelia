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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Coupon\CouponCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Coupon as CouponModel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Model\Map\CategoryTableMap;

/**
 * Created by JetBrains PhpStorm.
 * Date: 8/19/13
 * Time: 3:24 PM
 *
 * Process Coupon Events
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class Coupon extends BaseAction implements EventSubscriberInterface
{
    /**
     * Occurring when a Coupon is about to be created
     *
     * @param CouponCreateOrUpdateEvent $event Event creation or update Event
     */
    public function create(CouponCreateOrUpdateEvent $event)
    {
        $coupon = new CouponModel();

        $this->createOrUpdate($coupon, $event);
    }

    /**
     * Occurring when a Coupon is about to be updated
     *
     * @param CouponCreateOrUpdateEvent $event Event creation or update Event
     */
    public function update(CouponCreateOrUpdateEvent $event)
    {
        $coupon = $event->getCoupon();

        $this->createOrUpdate($coupon, $event);
    }

    /**
     * Occurring when a Coupon rule is about to be created
     *
     * @param CouponCreateOrUpdateEvent $event Event creation or update Event
     */
    public function createRule(CouponCreateOrUpdateEvent $event)
    {
        $coupon = $event->getCoupon();

        $this->createOrUpdate($coupon, $event);
    }

    /**
     * Occurring when a Coupon rule is about to be updated
     *
     * @param CouponCreateOrUpdateEvent $event Event creation or update Event
     */
    public function updateRule(CouponCreateOrUpdateEvent $event)
    {
        $coupon = $event->getCoupon();

        $this->createOrUpdate($coupon, $event);
    }

    /**
     * Occurring when a Coupon rule is about to be deleted
     *
     * @param CouponCreateOrUpdateEvent $event Event creation or update Event
     */
    public function deleteRule(CouponCreateOrUpdateEvent $event)
    {
        $coupon = $event->getCoupon();

        $this->createOrUpdate($coupon, $event);
    }

    /**
     * Occurring when a Coupon rule is about to be consumed
     *
     * @param CouponCreateOrUpdateEvent $event Event creation or update Event
     */
    public function consume(CouponCreateOrUpdateEvent $event)
    {
        // @todo implements
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
        $coupon->setDispatcher($this->getDispatcher());

        $coupon->createOrUpdate(
            $event->getCode(),
            $event->getTitle(),
            $event->getAmount(),
            $event->getEffect(),
            $event->getShortDescription(),
            $event->getDescription(),
            $event->isEnabled(),
            $event->getExpirationDate(),
            $event->isAvailableOnSpecialOffers(),
            $event->isCumulative(),
            $event->getMaxUsage(),
            $event->getRules(),
            $event->getLocale()
        );

        $event->setCoupon($coupon);
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
            TheliaEvents::COUPON_DISABLE => array("disable", 128),
            TheliaEvents::COUPON_ENABLE => array("enable", 128),
            TheliaEvents::COUPON_CONSUME => array("consume", 128),
            TheliaEvents::COUPON_RULE_UPDATE => array("updateRule", 128),
            TheliaEvents::COUPON_RULE_DELETE => array("deleteRule", 128)
        );
    }
}
