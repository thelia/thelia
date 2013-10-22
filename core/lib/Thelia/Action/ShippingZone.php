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
use Thelia\Core\Event\ShippingZone\ShippingZoneAddAreaEvent;
use Thelia\Core\Event\ShippingZone\ShippingZoneRemoveAreaEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\AreaDeliveryModuleQuery;

/**
 * Class ShippingZone
 * @package Thelia\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ShippingZone extends BaseAction implements EventSubscriberInterface
{

    public function addArea(ShippingZoneAddAreaEvent $event)
    {
        $areaDelivery = new AreaDeliveryModule();

        $areaDelivery
            ->setAreaId($event->getAreaId())
            ->setDeliveryModuleId($event->getShoppingZoneId())
            ->save();
    }

    public function removeArea(ShippingZoneRemoveAreaEvent $event)
    {
        $areaDelivery = AreaDeliveryModuleQuery::create()
            ->filterByAreaId($event->getAreaId())
            ->filterByDeliveryModuleId($event->getShoppingZoneId())
            ->findOne();

        if ($areaDelivery) {
            $areaDelivery->delete();
        } else {
            throw new \RuntimeException(sprintf('areaDeliveryModule not found with area_id = %d and delivery_module_id = %d', $event->getAreaId(), $event->getShoppingZoneId()));
        }
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
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
            TheliaEvents::SHIPPING_ZONE_ADD_AREA => array('addArea', 128),
            TheliaEvents::SHIPPING_ZONE_REMOVE_AREA => array('removeArea', 128),
        );
    }
}
