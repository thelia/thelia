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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Area\AreaAddCountryEvent;
use Thelia\Core\Event\Area\AreaCreateEvent;
use Thelia\Core\Event\Area\AreaDeleteEvent;
use Thelia\Core\Event\Area\AreaRemoveCountryEvent;
use Thelia\Core\Event\Area\AreaUpdateEvent;
use Thelia\Core\Event\Area\AreaUpdatePostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Area as AreaModel;
use Thelia\Model\AreaQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\CountryAreaQuery;

/**
 * Class Area
 * @package Thelia\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Area extends BaseAction implements EventSubscriberInterface
{
    public function addCountry(AreaAddCountryEvent $event)
    {
        $countryIds = $event->getCountryId();

        $areaId = $event->getAreaId();

        foreach ($countryIds as $countryId) {
            $countryArea = new CountryArea();

            $country = explode('-', $countryId);
            if (count($country) === 1) {
                $country[1] = null;
            }
            if ($country[1] == 0) {
                $country[1] = null;
            }

            $countryArea
                ->setAreaId($areaId)
                ->setCountryId($country[0])
                ->setStateId($country[1])
                ->save()
            ;
        }

        $event->setArea(AreaQuery::create()->findPk($areaId));
    }

    public function removeCountry(AreaRemoveCountryEvent $event)
    {
        CountryAreaQuery::create()
                ->filterByCountryId($event->getCountryId())
                ->filterByStateId($event->getStateId())
                ->filterByAreaId($event->getAreaId())
                ->delete();

        if (null !== $area = AreaQuery::create()->findPk($event->getAreaId())) {
            $event->setArea($area);
        }
    }

    public function updatePostage(AreaUpdatePostageEvent $event)
    {
        if (null !== $area = AreaQuery::create()->findPk($event->getAreaId())) {
            $area->setDispatcher($event->getDispatcher());
            $area
                ->setPostage($event->getPostage())
                ->save();

            $event->setArea($area);
        }
    }

    public function delete(AreaDeleteEvent $event)
    {
        if (null !== $area = AreaQuery::create()->findPk($event->getAreaId())) {
            $area->setDispatcher($event->getDispatcher());
            $area->delete();

            $event->setArea($area);
        }
    }

    public function create(AreaCreateEvent $event)
    {
        $area = new AreaModel();

        $area
            ->setDispatcher($event->getDispatcher())
            ->setName($event->getAreaName())
            ->save();

        $event->setArea($area);
    }

    public function update(AreaUpdateEvent $event)
    {
        if (null !== $area = AreaQuery::create()->findPk($event->getAreaId())) {
            $area
                ->setDispatcher($event->getDispatcher())
                ->setName($event->getAreaName())
                ->save();

            $event->setArea($area);
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
            TheliaEvents::AREA_ADD_COUNTRY => array('addCountry', 128),
            TheliaEvents::AREA_REMOVE_COUNTRY => array('removeCountry', 128),
            TheliaEvents::AREA_POSTAGE_UPDATE => array('updatePostage', 128),
            TheliaEvents::AREA_DELETE => array('delete', 128),
            TheliaEvents::AREA_CREATE => array('create', 128),
            TheliaEvents::AREA_UPDATE => array('update', 128)
        );
    }
}
