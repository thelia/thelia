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

use Thelia\Model\FeatureQuery;
use Thelia\Model\Feature as FeatureModel;

use Thelia\Core\Event\TheliaEvents;

use Thelia\Core\Event\Feature\FeatureUpdateEvent;
use Thelia\Core\Event\Feature\FeatureCreateEvent;
use Thelia\Core\Event\Feature\FeatureDeleteEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\Feature\FeatureEvent;
use Thelia\Model\FeatureTemplate;
use Thelia\Model\FeatureTemplateQuery;
use Thelia\Model\TemplateQuery;

class Feature extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new feature entry
     *
     * @param \Thelia\Core\Event\Feature\FeatureCreateEvent $event
     */
    public function create(FeatureCreateEvent $event)
    {
        $feature = new FeatureModel();

        $feature
            ->setDispatcher($event->getDispatcher())

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
     * Change a product feature
     *
     * @param \Thelia\Core\Event\Feature\FeatureUpdateEvent $event
     */
    public function update(FeatureUpdateEvent $event)
    {
        if (null !== $feature = FeatureQuery::create()->findPk($event->getFeatureId())) {
            $feature
                ->setDispatcher($event->getDispatcher())

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
     * Delete a product feature entry
     *
     * @param FeatureDeleteEvent $event
     */
    public function delete(FeatureDeleteEvent $event)
    {
        if (null !== ($feature = FeatureQuery::create()->findPk($event->getFeatureId()))) {
            $feature
                ->setDispatcher($event->getDispatcher())
                ->delete()
            ;

            $event->setFeature($feature);
        }
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param UpdatePositionEvent $event
     */
    public function updatePosition(UpdatePositionEvent $event)
    {
        $this->genericUpdatePosition(FeatureQuery::create(), $event);
    }

    protected function doAddToAllTemplates(FeatureModel $feature)
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

    public function addToAllTemplates(FeatureEvent $event)
    {
        $this->doAddToAllTemplates($event->getFeature());
    }

    public function removeFromAllTemplates(FeatureEvent $event)
    {
        // Delete this feature from all product templates
        FeatureTemplateQuery::create()->filterByFeature($event->getFeature())->delete();
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::FEATURE_CREATE          => array("create", 128),
            TheliaEvents::FEATURE_UPDATE          => array("update", 128),
            TheliaEvents::FEATURE_DELETE          => array("delete", 128),
            TheliaEvents::FEATURE_UPDATE_POSITION => array("updatePosition", 128),

            TheliaEvents::FEATURE_REMOVE_FROM_ALL_TEMPLATES => array("removeFromAllTemplates", 128),
            TheliaEvents::FEATURE_ADD_TO_ALL_TEMPLATES      => array("addToAllTemplates", 128),

        );
    }
}
