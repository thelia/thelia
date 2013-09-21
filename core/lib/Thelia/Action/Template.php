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

use Thelia\Model\TemplateQuery;
use Thelia\Model\Template as TemplateModel;

use Thelia\Core\Event\TheliaEvents;

use Thelia\Core\Event\TemplateUpdateEvent;
use Thelia\Core\Event\TemplateCreateEvent;
use Thelia\Core\Event\TemplateDeleteEvent;
use Thelia\Model\ConfigQuery;
use Thelia\Model\TemplateAv;
use Thelia\Model\TemplateAvQuery;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\CategoryEvent;
use Thelia\Core\Event\TemplateEvent;
use Thelia\Model\TemplateTemplate;
use Thelia\Model\TemplateTemplateQuery;
use Thelia\Model\ProductQuery;
use Thelia\Core\Event\TemplateAddAttributeEvent;
use Thelia\Core\Event\TemplateDeleteAttributeEvent;
use Thelia\Model\AttributeTemplateQuery;
use Thelia\Model\AttributeTemplate;
use Thelia\Core\Event\TemplateDeleteFeatureEvent;
use Thelia\Core\Event\TemplateAddFeatureEvent;
use Thelia\Model\FeatureTemplateQuery;
use Thelia\Model\FeatureTemplate;

class Template extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new template entry
     *
     * @param TemplateCreateEvent $event
     */
    public function create(TemplateCreateEvent $event)
    {
        $template = new TemplateModel();

        $template
            ->setDispatcher($this->getDispatcher())

            ->setLocale($event->getLocale())
            ->setName($event->getTemplateName())

            ->save()
        ;

        $event->setTemplate($template);
    }

    /**
     * Change a product template
     *
     * @param TemplateUpdateEvent $event
     */
    public function update(TemplateUpdateEvent $event)
    {
        $search = TemplateQuery::create();

        if (null !== $template = TemplateQuery::create()->findPk($event->getTemplateId())) {

            $template
                ->setDispatcher($this->getDispatcher())

                 ->setLocale($event->getLocale())
                 ->setName($event->getTemplateName())
                 ->save();

            $event->setTemplate($template);
        }
    }

    /**
     * Delete a product template entry
     *
     * @param TemplateDeleteEvent $event
     */
    public function delete(TemplateDeleteEvent $event)
    {
        if (null !== ($template = TemplateQuery::create()->findPk($event->getTemplateId()))) {

            // Check if template is used by a product
            $product_count = ProductQuery::create()->findByTemplateId($template->getId())->count();

            if ($product_count <= 0) {
                $template
                    ->setDispatcher($this->getDispatcher())
                    ->delete()
                ;
            }

            $event->setTemplate($template);

            $event->setProductCount($product_count);
        }
    }

    public function addAttribute(TemplateAddAttributeEvent $event) {

        if (null === AttributeTemplateQuery::create()->filterByAttributeId($event->getAttributeId())->filterByTemplate($event->getTemplate())->findOne()) {

            $attribute_template = new AttributeTemplate();

            $attribute_template
                ->setAttributeId($event->getAttributeId())
                ->setTemplate($event->getTemplate())
            ->save()
            ;
        }
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param CategoryChangePositionEvent $event
     */
    public function updateAttributePosition(UpdatePositionEvent $event)
    {
        $attributeTemplate = AttributeTemplateQuery::create()->findPk($event->getObjectId());

        $this->updatePosition($attributeTemplate, $event);
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param CategoryChangePositionEvent $event
     */
    public function updateFeaturePosition(UpdatePositionEvent $event)
    {
        $featureTemplate = FeatureTemplateQuery::create()->findPk($event->getObjectId());

        $this->updatePosition($featureTemplate, $event);
    }

    protected function updatePosition($object, UpdatePositionEvent $event)
    {
        if (null !== $object) {

            $object->setDispatcher($this->getDispatcher());

            $mode = $event->getMode();

            if ($mode == UpdatePositionEvent::POSITION_ABSOLUTE)
                $object->changeAbsolutePosition($event->getPosition());
            else if ($mode == UpdatePositionEvent::POSITION_UP)
                $object->movePositionUp();
            else if ($mode == UpdatePositionEvent::POSITION_DOWN)
                $object->movePositionDown();
        }
    }

    public function deleteAttribute(TemplateDeleteAttributeEvent $event) {

        $attribute_template = AttributeTemplateQuery::create()
            ->filterByAttributeId($event->getAttributeId())
            ->filterByTemplate($event->getTemplate())->findOne()
        ;

        if ($attribute_template !== null) $attribute_template->delete();
    }

    public function addFeature(TemplateAddFeatureEvent $event) {

        if (null === FeatureTemplateQuery::create()->filterByFeatureId($event->getFeatureId())->filterByTemplate($event->getTemplate())->findOne()) {

            $feature_template = new FeatureTemplate();

            $feature_template
            ->setFeatureId($event->getFeatureId())
            ->setTemplate($event->getTemplate())
            ->save()
            ;
        }
    }

    public function deleteFeature(TemplateDeleteFeatureEvent $event) {

        $feature_template = FeatureTemplateQuery::create()
            ->filterByFeatureId($event->getFeatureId())
            ->filterByTemplate($event->getTemplate())->findOne()
        ;

        if ($feature_template !== null) $feature_template->delete();
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::TEMPLATE_CREATE          => array("create", 128),
            TheliaEvents::TEMPLATE_UPDATE          => array("update", 128),
            TheliaEvents::TEMPLATE_DELETE          => array("delete", 128),

            TheliaEvents::TEMPLATE_ADD_ATTRIBUTE    => array("addAttribute", 128),
            TheliaEvents::TEMPLATE_DELETE_ATTRIBUTE => array("deleteAttribute", 128),

            TheliaEvents::TEMPLATE_ADD_FEATURE    => array("addFeature", 128),
            TheliaEvents::TEMPLATE_DELETE_FEATURE => array("deleteFeature", 128),

            TheliaEvents::TEMPLATE_CHANGE_ATTRIBUTE_POSITION => array('updateAttributePosition', 128),
            TheliaEvents::TEMPLATE_CHANGE_FEATURE_POSITION   => array('updateFeaturePosition', 128),

        );
    }
}