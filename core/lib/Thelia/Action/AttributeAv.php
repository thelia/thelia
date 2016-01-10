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
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\AttributeAv as AttributeAvModel;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Attribute\AttributeAvUpdateEvent;
use Thelia\Core\Event\Attribute\AttributeAvCreateEvent;
use Thelia\Core\Event\Attribute\AttributeAvDeleteEvent;
use Thelia\Core\Event\UpdatePositionEvent;

class AttributeAv extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new attribute entry
     *
     * @param AttributeAvCreateEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function create(AttributeAvCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $attribute = new AttributeAvModel();

        $attribute
            ->setDispatcher($dispatcher)

            ->setAttributeId($event->getAttributeId())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())

            ->save()
        ;

        $event->setAttributeAv($attribute);
    }

    /**
     * Change a product attribute
     *
     * @param AttributeAvUpdateEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function update(AttributeAvUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $attribute = AttributeAvQuery::create()->findPk($event->getAttributeAvId())) {
            $attribute
                ->setDispatcher($dispatcher)

                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
                ->setChapo($event->getChapo())
                ->setPostscriptum($event->getPostscriptum())

                ->save();

            $event->setAttributeAv($attribute);
        }
    }

    /**
     * Delete a product attribute entry
     *
     * @param AttributeAvDeleteEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function delete(AttributeAvDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== ($attribute = AttributeAvQuery::create()->findPk($event->getAttributeAvId()))) {
            $attribute
                ->setDispatcher($dispatcher)
                ->delete()
            ;

            $event->setAttributeAv($attribute);
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
        $this->genericUpdatePosition(AttributeAvQuery::create(), $event);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::ATTRIBUTE_AV_CREATE          => array("create", 128),
            TheliaEvents::ATTRIBUTE_AV_UPDATE          => array("update", 128),
            TheliaEvents::ATTRIBUTE_AV_DELETE          => array("delete", 128),
            TheliaEvents::ATTRIBUTE_AV_UPDATE_POSITION => array("updatePosition", 128),
        );
    }
}
