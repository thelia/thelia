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
use Thelia\Core\Event\Feature\FeatureCreateEvent;
use Thelia\Core\Event\Feature\FeatureDeleteEvent;
use Thelia\Core\Event\Feature\FeatureEvent;
use Thelia\Core\Event\Feature\FeatureUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\Feature as FeatureModel;
use Thelia\Model\FeatureQuery;
use Thelia\Model\FeatureTemplate;
use Thelia\Model\FeatureTemplateQuery;
use Thelia\Model\TemplateQuery;

class Feature extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new feature entry.
     */
    public function create(FeatureCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $feature = new FeatureModel();

        $feature

            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())

            ->save()
        ;

        $event->setFeature($feature);

        // Add atribute to all product templates if required
        if ($event->getAddToAllTemplates() != 0) {
            $this->doAddToAllTemplates($feature);
        }
    }

    /**
     * Change a product feature.
     */
    public function update(FeatureUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $feature = FeatureQuery::create()->findPk($event->getFeatureId())) {
            $feature

                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
                ->setChapo($event->getChapo())
                ->setPostscriptum($event->getPostscriptum())

                ->save();

            $event->setFeature($feature);
        }
    }

    /**
     * Delete a product feature entry.
     */
    public function delete(FeatureDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== ($feature = FeatureQuery::create()->findPk($event->getFeatureId()))) {
            $feature

                ->delete()
            ;

            $event->setFeature($feature);
        }
    }

    /**
     * Changes position, selecting absolute ou relative change.
     */
    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericUpdatePosition(FeatureQuery::create(), $event, $dispatcher);
    }

    protected function doAddToAllTemplates(FeatureModel $feature): void
    {
        $templates = TemplateQuery::create()->find();

        foreach ($templates as $template) {
            $feature_template = new FeatureTemplate();

            if (null === FeatureTemplateQuery::create()->filterByFeature($feature)->filterByTemplate($template)->findOne()) {
                $feature_template
                    ->setFeature($feature)
                    ->setTemplate($template)
                    ->save()
                ;
            }
        }
    }

    public function addToAllTemplates(FeatureEvent $event): void
    {
        $this->doAddToAllTemplates($event->getFeature());
    }

    public function removeFromAllTemplates(FeatureEvent $event): void
    {
        // Delete this feature from all product templates
        FeatureTemplateQuery::create()->filterByFeature($event->getFeature())->delete();
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::FEATURE_CREATE => ['create', 128],
            TheliaEvents::FEATURE_UPDATE => ['update', 128],
            TheliaEvents::FEATURE_DELETE => ['delete', 128],
            TheliaEvents::FEATURE_UPDATE_POSITION => ['updatePosition', 128],

            TheliaEvents::FEATURE_REMOVE_FROM_ALL_TEMPLATES => ['removeFromAllTemplates', 128],
            TheliaEvents::FEATURE_ADD_TO_ALL_TEMPLATES => ['addToAllTemplates', 128],
        ];
    }
}
