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
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Coupon\CouponCreateEvent;
use Thelia\Core\Event\Coupon\CouponDisableEvent;
use Thelia\Core\Event\Coupon\CouponEnableEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Category as CategoryModel;
use Thelia\Form\CategoryCreationForm;
use Thelia\Core\Event\CategoryEvent;
use Thelia\Model\CouponQuery;
use Thelia\Tools\Redirect;
use Thelia\Model\CategoryQuery;
use Thelia\Model\AdminLog;
use Thelia\Form\CategoryDeletionForm;
use Thelia\Action\Exception\FormValidationException;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Model\Map\CategoryTableMap;
use Propel\Runtime\Exception\PropelException;

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
     * Create a Coupon if a Coupon creation attempt is found
     *
     * @param CouponCreateEvent $event Coupon creation Event
     */
    public function create(CouponCreateEvent $event)
    {
        $this->checkAuth("ADMIN", "admin.coupon.create");

        $this->dispatch(
            TheliaEvents::BEFORE_CREATE_COUPON,
            $event
        );

        $event->getCreatedCoupon()->save();

        $this->dispatch(
            TheliaEvents::AFTER_CREATE_COUPON,
            $event
        );
    }

    /**
     * Edit a Coupon if a Coupon edition attempt is found
     *
     * @param CouponEditEvent $event Coupon edition Event
     */
    public function edit(CouponEditEvent $event)
    {
        $this->checkAuth("ADMIN", "admin.coupon.edit");

        $this->dispatch(
            TheliaEvents::BEFORE_EDIT_COUPON,
            $event
        );

        $couponToUpdate = CouponQuery::create()->findPk($event->getId());

        if ($couponToUpdate !== null) {
            $event->getCreatedCoupon()->save();
        }

        $this->dispatch(
            TheliaEvents::AFTER_EDIT_COUPON,
            $event
        );
    }

    /**
     * Disable a Coupon if a Coupon disable attempt is found
     *
     * @param CouponDisableEvent $event Coupon disable Event
     */
    public function disable(CouponDisableEvent $event)
    {
        $this->checkAuth("ADMIN", "admin.coupon.disable");

        $couponToUpdate = CouponQuery::create()->findPk($event->getId());

        if ($couponToUpdate !== null) {
            $couponToUpdate->setIsEnabled(0);
            $event->getDispatcher()->dispatch(
                TheliaEvents::BEFORE_DISABLE_COUPON, $event
            );

            $couponToUpdate->save();

            $event->getDispatcher()->dispatch(
                TheliaEvents::AFTER_DISABLE_COUPON, $event
            );
        }
    }

    /**
     * Enable a Coupon if a Coupon enable attempt is found
     *
     * @param CouponEnableEvent $event Coupon enable Event
     */
    public function enable(CouponEnableEvent $event)
    {
        $this->checkAuth("ADMIN", "admin.coupon.enable");

        $couponToUpdate = CouponQuery::create()->findPk($event->getId());

        if ($couponToUpdate !== null) {
            $couponToUpdate->setIsEnabled(1);
            $event->getDispatcher()->dispatch(
                TheliaEvents::BEFORE_ENABLE_COUPON, $event
            );

            $couponToUpdate->save();

            $event->getDispatcher()->dispatch(
                TheliaEvents::AFTER_ENABLE_COUPON, $event
            );
        }
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
//            "action.createCategory" => array("create", 128),
//            "action.modifyCategory" => array("modify", 128),
//            "action.deleteCategory" => array("delete", 128),
//
//            "action.toggleCategoryVisibility" 	=> array("toggleVisibility", 128),
//            "action.changeCategoryPositionUp" 	=> array("changePositionUp", 128),
//            "action.changeCategoryPositionDown" => array("changePositionDown", 128),
//            "action.changeCategoryPosition" 	=> array("changePosition", 128),
        );
    }
}
