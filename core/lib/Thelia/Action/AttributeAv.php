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
     */
    public function create(AttributeAvCreateEvent $event)
    {
        $attribute = new AttributeAvModel();

        $attribute
            ->setDispatcher($event->getDispatcher())

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
     * @param \Thelia\Core\Event\Attribute\AttributeAvUpdateEvent $event
     */
    public function update(AttributeAvUpdateEvent $event)
    {
        if (null !== $attribute = AttributeAvQuery::create()->findPk($event->getAttributeAvId())) {
            $attribute
                ->setDispatcher($event->getDispatcher())

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
     */
    public function delete(AttributeAvDeleteEvent $event)
    {
        if (null !== ($attribute = AttributeAvQuery::create()->findPk($event->getAttributeAvId()))) {
            $attribute
                ->setDispatcher($event->getDispatcher())
                ->delete()
            ;

            $event->setAttributeAv($attribute);
        }
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param CategoryChangePositionEvent $event
     */
    public function updatePosition(UpdatePositionEvent $event)
    {
        $this->genericUpdatePosition(AttributeAvQuery::create(), $event);
    }

    /**
     * {@inheritDoc}
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
