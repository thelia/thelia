<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Feature\FeatureAvCreateEvent;
use Thelia\Core\Event\Feature\FeatureAvDeleteEvent;
use Thelia\Core\Event\Feature\FeatureAvUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\FeatureAv as FeatureAvModel;
use Thelia\Model\FeatureAvQuery;

class FeatureAv extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new feature entry
     *
     * @param $eventName
     */
    public function create(FeatureAvCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $feature = new FeatureAvModel();

        $feature

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
     * @param $eventName
     */
    public function update(FeatureAvUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $feature = FeatureAvQuery::create()->findPk($event->getFeatureAvId())) {
            $feature

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
     * @param $eventName
     */
    public function delete(FeatureAvDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== ($feature = FeatureAvQuery::create()->findPk($event->getFeatureAvId()))) {
            $feature

                ->delete()
            ;

            $event->setFeatureAv($feature);
        }
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param $eventName
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
        return [
            TheliaEvents::FEATURE_AV_CREATE          => ["create", 128],
            TheliaEvents::FEATURE_AV_UPDATE          => ["update", 128],
            TheliaEvents::FEATURE_AV_DELETE          => ["delete", 128],
            TheliaEvents::FEATURE_AV_UPDATE_POSITION => ["updatePosition", 128],
        ];
    }
}
