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

use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Template\TemplateAddAttributeEvent;
use Thelia\Core\Event\Template\TemplateAddFeatureEvent;
use Thelia\Core\Event\Template\TemplateCreateEvent;
use Thelia\Core\Event\Template\TemplateDeleteAttributeEvent;
use Thelia\Core\Event\Template\TemplateDeleteEvent;
use Thelia\Core\Event\Template\TemplateDeleteFeatureEvent;
use Thelia\Core\Event\Template\TemplateDuplicateEvent;
use Thelia\Core\Event\Template\TemplateUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Translation\Translator;
use Thelia\Model\AttributeTemplate;
use Thelia\Model\AttributeTemplateQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\FeatureTemplate;
use Thelia\Model\FeatureTemplateQuery;
use Thelia\Model\Map\TemplateTableMap;
use Thelia\Model\ProductQuery;
use Thelia\Model\Template as TemplateModel;
use Thelia\Model\TemplateQuery;

class Template extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new template entry.
     */
    public function create(TemplateCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $template = new TemplateModel();

        $template

            ->setLocale($event->getLocale())
            ->setName($event->getTemplateName())

            ->save()
        ;

        $event->setTemplate($template);
    }

    /**
     * Dupliucate an existing template entry.
     *
     * @param \Thelia\Core\Event\Template\TemplateCreateEvent $event
     */
    public function duplicate(TemplateDuplicateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $source = TemplateQuery::create()->findPk($event->getSourceTemplateId())) {
            $source->setLocale($event->getLocale());

            $createEvent = new TemplateCreateEvent();
            $createEvent
                ->setLocale($event->getLocale())
                ->setTemplateName(
                    Translator::getInstance()->trans('Copy of %tpl', ['%tpl' => $source->getName()])
                );

            $dispatcher->dispatch($createEvent, TheliaEvents::TEMPLATE_CREATE);

            $clone = $createEvent->getTemplate();

            $attrList = AttributeTemplateQuery::create()->findByTemplateId($source->getId());

            /** @var $feat AttributeTemplate */
            foreach ($attrList as $feat) {
                $dispatcher->dispatch(
                    new TemplateAddAttributeEvent($clone, $feat->getAttributeId()),
                    TheliaEvents::TEMPLATE_ADD_ATTRIBUTE
                );
            }

            $featList = FeatureTemplateQuery::create()->findByTemplateId($source->getId());

            /** @var $feat FeatureTemplate */
            foreach ($featList as $feat) {
                $dispatcher->dispatch(
                    new TemplateAddFeatureEvent($clone, $feat->getFeatureId()),
                    TheliaEvents::TEMPLATE_ADD_FEATURE
                );
            }

            $event->setTemplate($clone);
        }
    }

    /**
     * Change a product template.
     */
    public function update(TemplateUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $template = TemplateQuery::create()->findPk($event->getTemplateId())) {
            $template

                ->setLocale($event->getLocale())
                ->setName($event->getTemplateName())
                ->save();

            $event->setTemplate($template);
        }
    }

    /**
     * Delete a product template entry.
     *
     * @throws \Exception
     */
    public function delete(TemplateDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== ($template = TemplateQuery::create()->findPk($event->getTemplateId()))) {
            // Check if template is used by a product
            $productCount = ProductQuery::create()->findByTemplateId($template->getId())->count();

            if ($productCount <= 0) {
                $con = Propel::getWriteConnection(TemplateTableMap::DATABASE_NAME);
                $con->beginTransaction();

                try {
                    $template

                        ->delete($con);

                    // We have to also delete any reference of this template in category tables
                    // We can't use a FK here, as the DefaultTemplateId column may be NULL
                    // so let's take care of this.
                    CategoryQuery::create()
                        ->filterByDefaultTemplateId($event->getTemplateId())
                        ->update(['DefaultTemplateId' => null], $con);

                    $con->commit();
                } catch (\Exception $ex) {
                    $con->rollback();

                    throw $ex;
                }
            }

            $event->setTemplate($template);

            $event->setProductCount($productCount);
        }
    }

    public function addAttribute(TemplateAddAttributeEvent $event): void
    {
        if (null === AttributeTemplateQuery::create()
                ->filterByAttributeId($event->getAttributeId())
                ->filterByTemplate($event->getTemplate())
                ->findOne()) {
            $attributeTemplate = new AttributeTemplate();

            $attributeTemplate
                ->setAttributeId($event->getAttributeId())
                ->setTemplate($event->getTemplate())
                ->save()
            ;
        }
    }

    /**
     * Changes position, selecting absolute ou relative change.
     */
    public function updateAttributePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericUpdatePosition(AttributeTemplateQuery::create(), $event, $dispatcher);
    }

    /**
     * Changes position, selecting absolute ou relative change.
     */
    public function updateFeaturePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericUpdatePosition(FeatureTemplateQuery::create(), $event, $dispatcher);
    }

    public function deleteAttribute(TemplateDeleteAttributeEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $attributeTemplate = AttributeTemplateQuery::create()
            ->filterByAttributeId($event->getAttributeId())
            ->filterByTemplate($event->getTemplate())->findOne()
        ;

        if ($attributeTemplate !== null) {
            $attributeTemplate

                ->delete();
        } else {
            // Prevent event propagation
            $event->stopPropagation();
        }
    }

    public function addFeature(TemplateAddFeatureEvent $event): void
    {
        if (null === FeatureTemplateQuery::create()
                ->filterByFeatureId($event->getFeatureId())
                ->filterByTemplate($event->getTemplate())
                ->findOne()
        ) {
            $featureTemplate = new FeatureTemplate();

            $featureTemplate
                ->setFeatureId($event->getFeatureId())
                ->setTemplate($event->getTemplate())
                ->save()
            ;
        }
    }

    public function deleteFeature(TemplateDeleteFeatureEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $featureTemplate = FeatureTemplateQuery::create()
            ->filterByFeatureId($event->getFeatureId())
            ->filterByTemplate($event->getTemplate())->findOne()
        ;

        if ($featureTemplate !== null) {
            $featureTemplate

                ->delete();
        } else {
            // Prevent event propagation
            $event->stopPropagation();
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::TEMPLATE_CREATE => ['create', 128],
            TheliaEvents::TEMPLATE_UPDATE => ['update', 128],
            TheliaEvents::TEMPLATE_DELETE => ['delete', 128],
            TheliaEvents::TEMPLATE_DUPLICATE => ['duplicate', 128],

            TheliaEvents::TEMPLATE_ADD_ATTRIBUTE => ['addAttribute', 128],
            TheliaEvents::TEMPLATE_DELETE_ATTRIBUTE => ['deleteAttribute', 128],

            TheliaEvents::TEMPLATE_ADD_FEATURE => ['addFeature', 128],
            TheliaEvents::TEMPLATE_DELETE_FEATURE => ['deleteFeature', 128],

            TheliaEvents::TEMPLATE_CHANGE_ATTRIBUTE_POSITION => ['updateAttributePosition', 128],
            TheliaEvents::TEMPLATE_CHANGE_FEATURE_POSITION => ['updateFeaturePosition', 128],
        ];
    }
}
