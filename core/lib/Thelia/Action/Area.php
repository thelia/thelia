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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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

    public function updatePostage(AreaUpdatePostageEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $area = AreaQuery::create()->findPk($event->getAreaId())) {
            $area->setDispatcher($dispatcher);
            $area
                ->setPostage($event->getPostage())
                ->save();

            $event->setArea($area);
        }
    }

    public function delete(AreaDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $area = AreaQuery::create()->findPk($event->getAreaId())) {
            $area->setDispatcher($dispatcher);
            $area->delete();

            $event->setArea($area);
        }
    }

    public function create(AreaCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $area = new AreaModel();

        $area
            ->setDispatcher($dispatcher)
            ->setName($event->getAreaName())
            ->save();

        $event->setArea($area);
    }

    public function update(AreaUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $area = AreaQuery::create()->findPk($event->getAreaId())) {
            $area
                ->setDispatcher($dispatcher)
                ->setName($event->getAreaName())
                ->save();

            $event->setArea($area);
        }
    }

    /**
     * {@inheritdoc}
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
