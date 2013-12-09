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

use Thelia\Exception\UrlRewritingException;
use Thelia\Form\Exception\FormValidationException;
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
            ->setDispatcher($this->getDispatcher())

            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->setParent($event->getParent())
            ->setVisible($event->getVisible())

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
        $search = CategoryQuery::create();

        if (null !== $category = CategoryQuery::create()->findPk($event->getCategoryId())) {

            $category
                ->setDispatcher($this->getDispatcher())

                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
                ->setChapo($event->getChapo())
                ->setPostscriptum($event->getPostscriptum())

                ->setParent($event->getParent())
                ->setVisible($event->getVisible())

                ->save();

            // Update the rewritten URL, if required
            try {
                $category->setRewrittenUrl($event->getLocale(), $event->getUrl());
            } catch(UrlRewritingException $e) {
                throw new FormValidationException($e->getMessage(), $e->getCode());
            }

            $event->setCategory($category);
        }
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
                ->setDispatcher($this->getDispatcher())
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
            ->setDispatcher($this->getDispatcher())
            ->setVisible($category->getVisible() ? false : true)
            ->save()
            ;
    }

    /**
     * Changes position, selecting absolute ou relative change.
     *
     * @param CategoryChangePositionEvent $event
     */
    public function updatePosition(UpdatePositionEvent $event)
    {
        return $this->genericUpdatePosition(CategoryQuery::create(), $event);
    }

    public function addContent(CategoryAddContentEvent $event)
    {
        if (CategoryAssociatedContentQuery::create()
            ->filterByContentId($event->getContentId())
             ->filterByCategory($event->getCategory())->count() <= 0) {

            $content = new CategoryAssociatedContent();

            $content
                ->setDispatcher($this->getDispatcher())
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
                ->setDispatcher($this->getDispatcher())
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

            TheliaEvents::CATEGORY_ADD_CONTENT       => array("addContent", 128),
            TheliaEvents::CATEGORY_REMOVE_CONTENT    => array("removeContent", 128),

        );
    }
}
