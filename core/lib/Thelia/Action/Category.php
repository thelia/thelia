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
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Model\CategoryDocumentQuery;
use Thelia\Model\CategoryImageQuery;
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
use Thelia\Model\Map\CategoryTableMap;

class Category extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new category entry
     *
     * @param \Thelia\Core\Event\Category\CategoryCreateEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function create(CategoryCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $category = new CategoryModel();

        $category
            ->setDispatcher($dispatcher)

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
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function update(CategoryUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $category = CategoryQuery::create()->findPk($event->getCategoryId())) {
            $category
                ->setDispatcher($dispatcher)
                ->setDefaultTemplateId($event->getDefaultTemplateId() == 0 ? null : $event->getDefaultTemplateId())
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
     * @param UpdateSeoEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     * @return Object
     */
    public function updateSeo(UpdateSeoEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        return $this->genericUpdateSeo(CategoryQuery::create(), $event, $dispatcher);
    }

    /**
     * Delete a category entry
     *
     * @param \Thelia\Core\Event\Category\CategoryDeleteEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     * @throws \Exception
     */
    public function delete(CategoryDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $category = CategoryQuery::create()->findPk($event->getCategoryId())) {
            $con = Propel::getWriteConnection(CategoryTableMap::DATABASE_NAME);
            $con->beginTransaction();

            try {
                $fileList = ['images' => [], 'documentList' => []];

                // Get category's files to delete after category deletion
                $fileList['images']['list'] = CategoryImageQuery::create()
                    ->findByCategoryId($event->getCategoryId());
                $fileList['images']['type'] = TheliaEvents::IMAGE_DELETE;

                $fileList['documentList']['list'] = CategoryDocumentQuery::create()
                    ->findByCategoryId($event->getCategoryId());
                $fileList['documentList']['type'] = TheliaEvents::DOCUMENT_DELETE;

                // Delete category
                $category
                    ->setDispatcher($dispatcher)
                    ->delete($con);

                $event->setCategory($category);

                // Dispatch delete category's files event
                foreach ($fileList as $fileTypeList) {
                    foreach ($fileTypeList['list'] as $fileToDelete) {
                        $fileDeleteEvent = new FileDeleteEvent($fileToDelete);
                        $dispatcher->dispatch($fileTypeList['type'], $fileDeleteEvent);
                    }
                }

                $con->commit();
            } catch (\Exception $e) {
                $con->rollback();
                throw $e;
            }
        }
    }

    /**
     * Toggle category visibility. No form used here
     *
     * @param CategoryToggleVisibilityEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function toggleVisibility(CategoryToggleVisibilityEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $category = $event->getCategory();

        $category
            ->setDispatcher($dispatcher)
            ->setVisible($category->getVisible() ? false : true)
            ->save()
            ;

        $event->setCategory($category);
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
        $this->genericUpdatePosition(CategoryQuery::create(), $event, $dispatcher);
    }

    public function addContent(CategoryAddContentEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (CategoryAssociatedContentQuery::create()
            ->filterByContentId($event->getContentId())
             ->filterByCategory($event->getCategory())->count() <= 0) {
            $content = new CategoryAssociatedContent();

            $content
                ->setDispatcher($dispatcher)
                ->setCategory($event->getCategory())
                ->setContentId($event->getContentId())
                ->save()
            ;
        }
    }

    public function removeContent(CategoryDeleteContentEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $content = CategoryAssociatedContentQuery::create()
            ->filterByContentId($event->getContentId())
            ->filterByCategory($event->getCategory())->findOne()
        ;

        if ($content !== null) {
            $content
                ->setDispatcher($dispatcher)
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
