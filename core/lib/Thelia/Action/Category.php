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
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Category as CategoryModel;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Category\CategoryUpdateEvent;
use Thelia\Core\Event\Category\CategoryCreateEvent;
use Thelia\Core\Event\Category\CategoryDeleteEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\Category\CategoryToggleVisibilityEvent;
use Thelia\Core\Event\Category\CategoryAddContentEvent;
use Thelia\Core\Event\Category\CategoryDeleteContentEvent;
use Thelia\Model\CategoryAssociatedContent;
use Thelia\Model\CategoryAssociatedContentQuery;

class Category extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new category entry
     *
     * @param \Thelia\Core\Event\Category\CategoryCreateEvent $event
     */
    public function create(CategoryCreateEvent $event)
    {
        $category = new CategoryModel();

        $category
            ->setDispatcher($event->getDispatcher())

            ->setLocale($event->getLocale())
            ->setParent($event->getParent())
            ->setVisible($event->getVisible())
            ->setTitle($event->getTitle())

            ->save()
        ;

        $event->setCategory($category);
    }

    /**
     * Change a category
     *
     * @param \Thelia\Core\Event\Category\CategoryUpdateEvent $event
     */
    public function update(CategoryUpdateEvent $event)
    {
        if (null !== $category = CategoryQuery::create()->findPk($event->getCategoryId())) {
            $category
                ->setDispatcher($event->getDispatcher())

                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
                ->setChapo($event->getChapo())
                ->setPostscriptum($event->getPostscriptum())

                ->setParent($event->getParent())
                ->setVisible($event->getVisible())

                ->save();

            $event->setCategory($category);
        }
    }

    /**
     * Change a Category SEO
     *
     * @param \Thelia\Core\Event\UpdateSeoEvent $event
     *
     * @return mixed
     */
    public function updateSeo(UpdateSeoEvent $event)
    {
        return $this->genericUpdateSeo(CategoryQuery::create(), $event);
    }

    /**
     * Delete a category entry
     *
     * @param \Thelia\Core\Event\Category\CategoryDeleteEvent $event
     */
    public function delete(CategoryDeleteEvent $event)
    {
        if (null !== $category = CategoryQuery::create()->findPk($event->getCategoryId())) {
            $category
                ->setDispatcher($event->getDispatcher())
                ->delete()
            ;

            $event->setCategory($category);
        }
    }

    /**
     * Toggle category visibility. No form used here
     *
     * @param ActionEvent $event
     */
    public function toggleVisibility(CategoryToggleVisibilityEvent $event)
    {
        $category = $event->getCategory();

        $category
            ->setDispatcher($event->getDispatcher())
            ->setVisible($category->getVisible() ? false : true)
            ->save()
            ;

        $event->setCategory($category);
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param CategoryChangePositionEvent $event
     */
    public function updatePosition(UpdatePositionEvent $event)
    {
        $this->genericUpdatePosition(CategoryQuery::create(), $event);
    }

    public function addContent(CategoryAddContentEvent $event)
    {
        if (CategoryAssociatedContentQuery::create()
            ->filterByContentId($event->getContentId())
             ->filterByCategory($event->getCategory())->count() <= 0) {
            $content = new CategoryAssociatedContent();

            $content
                ->setDispatcher($event->getDispatcher())
                ->setCategory($event->getCategory())
                ->setContentId($event->getContentId())
                ->save()
            ;
        }
    }

    public function removeContent(CategoryDeleteContentEvent $event)
    {
        $content = CategoryAssociatedContentQuery::create()
            ->filterByContentId($event->getContentId())
            ->filterByCategory($event->getCategory())->findOne()
        ;

        if ($content !== null) {
            $content
                ->setDispatcher($event->getDispatcher())
                ->delete();
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::CATEGORY_CREATE            => array("create", 128),
            TheliaEvents::CATEGORY_UPDATE            => array("update", 128),
            TheliaEvents::CATEGORY_DELETE            => array("delete", 128),
            TheliaEvents::CATEGORY_TOGGLE_VISIBILITY => array("toggleVisibility", 128),

            TheliaEvents::CATEGORY_UPDATE_POSITION   => array("updatePosition", 128),
            TheliaEvents::CATEGORY_UPDATE_SEO        => array("updateSeo", 128),

            TheliaEvents::CATEGORY_ADD_CONTENT       => array("addContent", 128),
            TheliaEvents::CATEGORY_REMOVE_CONTENT    => array("removeContent", 128),

        );
    }
}
