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

use Thelia\Model\FeatureAvQuery;
use Thelia\Model\FeatureAv as FeatureAvModel;

use Thelia\Core\Event\TheliaEvents;

use Thelia\Core\Event\Feature\FeatureAvUpdateEvent;
use Thelia\Core\Event\Feature\FeatureAvCreateEvent;
use Thelia\Core\Event\Feature\FeatureAvDeleteEvent;
use Thelia\Core\Event\UpdatePositionEvent;

class FeatureAv extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new feature entry
     *
     * @param FeatureAvCreateEvent $event
     */
    public function create(FeatureAvCreateEvent $event)
    {
        $feature = new FeatureAvModel();

        $feature
            ->setDispatcher($event->getDispatcher())

            ->setFeatureId($event->getFeatureId())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())

            ->save()
        ;

        $event->setFeatureAv($feature);
    }

    /**
     * Change a product feature
     *
     * @param FeatureAvUpdateEvent $event
     */
    public function update(FeatureAvUpdateEvent $event)
    {

        if (null !== $feature = FeatureAvQuery::create()->findPk($event->getFeatureAvId())) {

            $feature
                ->setDispatcher($event->getDispatcher())

                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
                ->setChapo($event->getChapo())
                ->setPostscriptum($event->getPostscriptum())

                ->save();

            $event->setFeatureAv($feature);
        }
    }

    /**
     * Delete a product feature entry
     *
     * @param FeatureAvDeleteEvent $event
     */
    public function delete(FeatureAvDeleteEvent $event)
    {

        if (null !== ($feature = FeatureAvQuery::create()->findPk($event->getFeatureAvId()))) {

            $feature
                ->setDispatcher($event->getDispatcher())
                ->delete()
            ;

            $event->setFeatureAv($feature);
        }
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param UpdatePositionEvent $event
     */
    public function updatePosition(UpdatePositionEvent $event)
    {
        $this->genericUpdatePosition(FeatureAvQuery::create(), $event);
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::FEATURE_AV_CREATE          => array("create", 128),
            TheliaEvents::FEATURE_AV_UPDATE          => array("update", 128),
            TheliaEvents::FEATURE_AV_DELETE          => array("delete", 128),
            TheliaEvents::FEATURE_AV_UPDATE_POSITION => array("updatePosition", 128),
        );
    }
}
