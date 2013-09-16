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

use Thelia\Model\AttributeQuery;
use Thelia\Model\Attribute as AttributeModel;

use Thelia\Core\Event\TheliaEvents;

use Thelia\Core\Event\AttributeUpdateEvent;
use Thelia\Core\Event\AttributeCreateEvent;
use Thelia\Core\Event\AttributeDeleteEvent;
use Thelia\Model\ConfigQuery;
use Thelia\Model\AttributeAv;
use Thelia\Model\AttributeAvQuery;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\CategoryEvent;
use Thelia\Core\Event\AttributeEvent;
use Thelia\Model\AttributeTemplate;
use Thelia\Model\AttributeTemplateQuery;

class Attribute extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new attribute entry
     *
     * @param AttributeCreateEvent $event
     */
    public function create(AttributeCreateEvent $event)
    {
        $attribute = new AttributeModel();

        $attribute
            ->setDispatcher($this->getDispatcher())

            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())

            ->save()
        ;

        $event->setAttribute($attribute);

        // Add atribute to all product templates if required
        if ($event->getAddToAllTemplates() != 0) {
            // TODO: add to all product template
        }
    }

    /**
     * Change a product attribute
     *
     * @param AttributeUpdateEvent $event
     */
    public function update(AttributeUpdateEvent $event)
    {
        $search = AttributeQuery::create();

        if (null !== $attribute = AttributeQuery::create()->findPk($event->getAttributeId())) {

            $attribute
                ->setDispatcher($this->getDispatcher())

                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
                ->setChapo($event->getChapo())
                ->setPostscriptum($event->getPostscriptum())

                ->save();

            $event->setAttribute($attribute);
        }
    }

    /**
     * Delete a product attribute entry
     *
     * @param AttributeDeleteEvent $event
     */
    public function delete(AttributeDeleteEvent $event)
    {

        if (null !== ($attribute = AttributeQuery::create()->findPk($event->getAttributeId()))) {

            $attribute
                ->setDispatcher($this->getDispatcher())
                ->delete()
            ;

            $event->setAttribute($attribute);
        }
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param CategoryChangePositionEvent $event
     */
    public function updatePosition(UpdatePositionEvent $event)
    {
        if (null !== $attribute = AttributeQuery::create()->findPk($event->getObjectId())) {

            $attribute->setDispatcher($this->getDispatcher());

            $mode = $event->getMode();

            if ($mode == UpdatePositionEvent::POSITION_ABSOLUTE)
                return $attribute->changeAbsolutePosition($event->getPosition());
            else if ($mode == UpdatePositionEvent::POSITION_UP)
                return $attribute->movePositionUp();
            else if ($mode == UpdatePositionEvent::POSITION_DOWN)
                return $attribute->movePositionDown();
        }
    }

    public function addToAllTemplates(AttributeEvent $event)
    {
        $templates = AttributeTemplateQuery::create()->find();

        foreach($templates as $template) {
            $pat = new AttributeTemplate();

            $pat->setTemplate($template->getId())
                ->setAttributeId($event->getAttribute()->getId())
                ->save();
        }
    }

    public function removeFromAllTemplates(AttributeEvent $event)
    {
        // Delete this attribute from all product templates
        AttributeTemplateQuery::create()->filterByAttributeId($event->getAttribute()->getId())->delete();
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::ATTRIBUTE_CREATE          => array("create", 128),
            TheliaEvents::ATTRIBUTE_UPDATE          => array("update", 128),
            TheliaEvents::ATTRIBUTE_DELETE          => array("delete", 128),
            TheliaEvents::ATTRIBUTE_UPDATE_POSITION => array("updatePosition", 128),

            TheliaEvents::ATTRIBUTE_REMOVE_FROM_ALL_TEMPLATES => array("removeFromAllTemplates", 128),
            TheliaEvents::ATTRIBUTE_ADD_TO_ALL_TEMPLATES      => array("addToAllTemplates", 128),

        );
    }
}