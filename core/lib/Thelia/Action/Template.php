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

use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Map\TemplateTableMap;
use Thelia\Model\TemplateQuery;
use Thelia\Model\Template as TemplateModel;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Template\TemplateUpdateEvent;
use Thelia\Core\Event\Template\TemplateCreateEvent;
use Thelia\Core\Event\Template\TemplateDeleteEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\ProductQuery;
use Thelia\Core\Event\Template\TemplateAddAttributeEvent;
use Thelia\Core\Event\Template\TemplateDeleteAttributeEvent;
use Thelia\Model\AttributeTemplateQuery;
use Thelia\Model\AttributeTemplate;
use Thelia\Core\Event\Template\TemplateDeleteFeatureEvent;
use Thelia\Core\Event\Template\TemplateAddFeatureEvent;
use Thelia\Model\FeatureTemplateQuery;
use Thelia\Model\FeatureTemplate;

class Template extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new template entry
     *
     * @param \Thelia\Core\Event\Template\TemplateCreateEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function create(TemplateCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $template = new TemplateModel();

        $template
            ->setDispatcher($dispatcher)

            ->setLocale($event->getLocale())
            ->setName($event->getTemplateName())

            ->save()
        ;

        $event->setTemplate($template);
    }

    /**
     * Change a product template
     *
     * @param \Thelia\Core\Event\Template\TemplateUpdateEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function update(TemplateUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $template = TemplateQuery::create()->findPk($event->getTemplateId())) {
            $template
                ->setDispatcher($dispatcher)

                 ->setLocale($event->getLocale())
                 ->setName($event->getTemplateName())
                 ->save();

            $event->setTemplate($template);
        }
    }

    /**
     * Delete a product template entry
     *
     * @param \Thelia\Core\Event\Template\TemplateDeleteEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     * @throws \Exception
     */
    public function delete(TemplateDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== ($template = TemplateQuery::create()->findPk($event->getTemplateId()))) {
            // Check if template is used by a product
            $product_count = ProductQuery::create()->findByTemplateId($template->getId())->count();

            if ($product_count <= 0) {
                $con = Propel::getWriteConnection(TemplateTableMap::DATABASE_NAME);
                $con->beginTransaction();

                try {
                    $template
                        ->setDispatcher($dispatcher)
                        ->delete($con);

                    // We have to also delete any reference of this template in category tables
                    // We can't use a FK here, as the DefaultTemplateId column may be NULL
                    // so let's take care of this.
                    CategoryQuery::create()
                        ->filterByDefaultTemplateId($event->getTemplateId())
                        ->update([ 'DefaultTemplateId' => null], $con);

                    $con->commit();
                } catch (\Exception $ex) {
                    $con->rollback();

                    throw $ex;
                }
            }

            $event->setTemplate($template);

            $event->setProductCount($product_count);
        }
    }

    public function addAttribute(TemplateAddAttributeEvent $event)
    {
        if (null === AttributeTemplateQuery::create()
                ->filterByAttributeId($event->getAttributeId())
                ->filterByTemplate($event->getTemplate())
                ->findOne()) {
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
     * @param UpdatePositionEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function updateAttributePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $this->genericUpdatePosition(AttributeTemplateQuery::create(), $event, $dispatcher);
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param UpdatePositionEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function updateFeaturePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $this->genericUpdatePosition(FeatureTemplateQuery::create(), $event, $dispatcher);
    }

    public function deleteAttribute(TemplateDeleteAttributeEvent $event)
    {
        $attribute_template = AttributeTemplateQuery::create()
            ->filterByAttributeId($event->getAttributeId())
            ->filterByTemplate($event->getTemplate())->findOne()
        ;

        if ($attribute_template !== null) {
            $attribute_template->delete();
        }
    }

    public function addFeature(TemplateAddFeatureEvent $event)
    {
        if (null === FeatureTemplateQuery::create()
                ->filterByFeatureId($event->getFeatureId())
                ->filterByTemplate($event->getTemplate())
                ->findOne()
        ) {
            $feature_template = new FeatureTemplate();

            $feature_template
            ->setFeatureId($event->getFeatureId())
            ->setTemplate($event->getTemplate())
            ->save()
            ;
        }
    }

    public function deleteFeature(TemplateDeleteFeatureEvent $event)
    {
        $feature_template = FeatureTemplateQuery::create()
            ->filterByFeatureId($event->getFeatureId())
            ->filterByTemplate($event->getTemplate())->findOne()
        ;

        if ($feature_template !== null) {
            $feature_template->delete();
        }
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
