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
