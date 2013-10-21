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
use Thelia\Core\Event\Area\AreaAddCountryEvent;
use Thelia\Core\Event\Area\AreaCreateEvent;
use Thelia\Core\Event\Area\AreaDeleteEvent;
use Thelia\Core\Event\Area\AreaRemoveCountryEvent;
use Thelia\Core\Event\Area\AreaUpdatePostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\AreaQuery;
use Thelia\Model\CountryQuery;
use Thelia\Action\BaseAction;
use Thelia\Model\Area as AreaModel;

/**
 * Class Area
 * @package Thelia\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Area extends BaseAction implements EventSubscriberInterface
{

    public function addCountry(AreaAddCountryEvent $event)
    {
        if (null !== $country = CountryQuery::create()->findPk($event->getCountryId())) {
            $country->setDispatcher($this->getDispatcher());
            $country->setAreaId($event->getAreaId())
                ->save();

            $event->setArea($country->getArea());
        }
    }

    public function removeCountry(AreaRemoveCountryEvent $event)
    {
        if (null !== $country = CountryQuery::create()->findPk($event->getCountryId())) {
            $country->setDispatcher($this->getDispatcher());
            $country->setAreaId(null)
                ->save();
        }
    }

    public function updatePostage(AreaUpdatePostageEvent $event)
    {
        if (null !== $area = AreaQuery::create()->findPk($event->getAreaId())) {
            $area->setDispatcher($this->getDispatcher());
            $area
                ->setPostage($event->getPostage())
                ->save();

            $event->setArea($area);
        }
    }

    public function delete(AreaDeleteEvent $event)
    {
        if (null !== $area = AreaQuery::create()->findPk($event->getAreaId())) {
            $area->setDispatcher($this->getDispatcher());
            $area->delete();

            $event->setArea($area);
        }
    }

    public function create(AreaCreateEvent $event)
    {
        $area = new AreaModel();

        $area
            ->setDispatcher($this->getDispatcher())
            ->setName($event->getAreaName())
            ->save();

        $event->setArea($area);
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
            TheliaEvents::AREA_ADD_COUNTRY => array('addCountry', 128),
            TheliaEvents::AREA_REMOVE_COUNTRY => array('removeCountry', 128),
            TheliaEvents::AREA_POSTAGE_UPDATE => array('updatePostage', 128),
            TheliaEvents::AREA_DELETE => array('delete', 128),
            TheliaEvents::AREA_CREATE => array('create', 128)
        );
    }
}
