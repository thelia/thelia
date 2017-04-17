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
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function create(FeatureAvCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $feature = new FeatureAvModel();

        $feature
            ->setDispatcher($dispatcher)

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
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function update(FeatureAvUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $feature = FeatureAvQuery::create()->findPk($event->getFeatureAvId())) {
            $feature
                ->setDispatcher($dispatcher)

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
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function delete(FeatureAvDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== ($feature = FeatureAvQuery::create()->findPk($event->getFeatureAvId()))) {
            $feature
                ->setDispatcher($dispatcher)
                ->delete()
            ;

            $event->setFeatureAv($feature);
        }
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param UpdatePositionEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $this->genericUpdatePosition(FeatureAvQuery::create(), $event, $dispatcher);
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
