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
use Thelia\Model\AttributeQuery;
use Thelia\Model\Attribute as AttributeModel;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Attribute\AttributeUpdateEvent;
use Thelia\Core\Event\Attribute\AttributeCreateEvent;
use Thelia\Core\Event\Attribute\AttributeDeleteEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\Attribute\AttributeEvent;
use Thelia\Model\AttributeTemplate;
use Thelia\Model\AttributeTemplateQuery;
use Thelia\Model\TemplateQuery;

class Attribute extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new attribute entry
     *
     * @param AttributeCreateEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function create(AttributeCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $attribute = new AttributeModel();

        $attribute
            ->setDispatcher($dispatcher)
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->save()
        ;

        $event->setAttribute($attribute);

        // Add atribute to all product templates if required
        if ($event->getAddToAllTemplates() != 0) {
            $this->doAddToAllTemplates($attribute);
        }
    }

    /**
     * Change a product attribute
     *
     * @param AttributeUpdateEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function update(AttributeUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $attribute = AttributeQuery::create()->findPk($event->getAttributeId())) {
            $attribute
                ->setDispatcher($dispatcher)

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
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function delete(AttributeDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== ($attribute = AttributeQuery::create()->findPk($event->getAttributeId()))) {
            $attribute
                ->setDispatcher($dispatcher)
                ->delete()
            ;

            $event->setAttribute($attribute);
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
        $this->genericUpdatePosition(AttributeQuery::create(), $event, $dispatcher);
    }

    protected function doAddToAllTemplates(AttributeModel $attribute)
    {
        $templates = TemplateQuery::create()->find();

        foreach ($templates as $template) {
            $attribute_template = new AttributeTemplate();

            if (null === AttributeTemplateQuery::create()->filterByAttribute($attribute)->filterByTemplate($template)->findOne()) {
                $attribute_template
                    ->setAttribute($attribute)
                    ->setTemplate($template)
                    ->save()
                ;
            }
        }
    }

    public function addToAllTemplates(AttributeEvent $event)
    {
        $this->doAddToAllTemplates($event->getAttribute());
    }

    public function removeFromAllTemplates(AttributeEvent $event)
    {
        // Delete this attribute from all product templates
        AttributeTemplateQuery::create()->filterByAttribute($event->getAttribute())->delete();
    }

    /**
     * {@inheritdoc}
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
