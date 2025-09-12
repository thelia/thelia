<?php

declare(strict_types=1);

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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Core\Event\Category\CategoryAddContentEvent;
use Thelia\Core\Event\Category\CategoryCreateEvent;
use Thelia\Core\Event\Category\CategoryDeleteContentEvent;
use Thelia\Core\Event\Category\CategoryDeleteEvent;
use Thelia\Core\Event\Category\CategoryToggleVisibilityEvent;
use Thelia\Core\Event\Category\CategoryUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Core\Event\ViewCheckEvent;
use Thelia\Model\Category as CategoryModel;
use Thelia\Model\CategoryAssociatedContent;
use Thelia\Model\CategoryAssociatedContentQuery;
use Thelia\Model\CategoryDocumentQuery;
use Thelia\Model\CategoryImageQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Map\CategoryTableMap;

class Category extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new category entry.
     */
    public function create(CategoryCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $category = new CategoryModel();

        $category
            ->setLocale($event->getLocale())
            ->setParent($event->getParent())
            ->setVisible($event->getVisible())
            ->setTitle($event->getTitle())

            ->save();

        $event->setCategory($category);
    }

    /**
     * Change a category.
     */
    public function update(CategoryUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $category = CategoryQuery::create()->findPk($event->getCategoryId())) {
            $category
                ->setDefaultTemplateId(0 === $event->getDefaultTemplateId() ? null : $event->getDefaultTemplateId())
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
     * Change a Category SEO.
     */
    public function updateSeo(UpdateSeoEvent $event, $eventName, EventDispatcherInterface $dispatcher): object
    {
        return $this->genericUpdateSeo(CategoryQuery::create(), $event, $dispatcher);
    }

    /**
     * Delete a category entry.
     *
     * @throws \Exception
     */
    public function delete(CategoryDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
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
                    ->delete($con);

                $event->setCategory($category);

                // Dispatch delete category's files event
                foreach ($fileList as $fileTypeList) {
                    foreach ($fileTypeList['list'] as $fileToDelete) {
                        $fileDeleteEvent = new FileDeleteEvent($fileToDelete);
                        $dispatcher->dispatch($fileDeleteEvent, $fileTypeList['type']);
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
     * Toggle category visibility. No form used here.
     */
    public function toggleVisibility(CategoryToggleVisibilityEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $category = $event->getCategory();

        $category
            ->setVisible(!(bool) $category->getVisible())
            ->save();

        $event->setCategory($category);
    }

    /**
     * Changes position, selecting absolute ou relative change.
     */
    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericUpdatePosition(CategoryQuery::create(), $event, $dispatcher);
    }

    public function addContent(CategoryAddContentEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (CategoryAssociatedContentQuery::create()
            ->filterByContentId($event->getContentId())
            ->filterByCategory($event->getCategory())->count() <= 0) {
            $content = new CategoryAssociatedContent();

            $content
                ->setCategory($event->getCategory())
                ->setContentId($event->getContentId())
                ->setPosition(1)
                ->save();
        }
    }

    public function removeContent(CategoryDeleteContentEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $content = CategoryAssociatedContentQuery::create()
            ->filterByContentId($event->getContentId())
            ->filterByCategory($event->getCategory())->findOne();

        if (null !== $content) {
            $content
                ->delete();
        }
    }

    /**
     * Check if is a category view and if category_id is visible.
     */
    public function viewCheck(ViewCheckEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        if ('category' === $event->getView()) {
            $category = CategoryQuery::create()
                ->filterById($event->getViewId())
                ->filterByVisible(1)
                ->count();

            if (0 === $category) {
                $dispatcher->dispatch($event, TheliaEvents::VIEW_CATEGORY_ID_NOT_VISIBLE);
            }
        }
    }

    /**
     * @throws NotFoundHttpException
     */
    public function viewcategoryIdNotVisible(ViewCheckEvent $event): void
    {
        throw new NotFoundHttpException();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CATEGORY_CREATE => ['create', 128],
            TheliaEvents::CATEGORY_UPDATE => ['update', 128],
            TheliaEvents::CATEGORY_DELETE => ['delete', 128],
            TheliaEvents::CATEGORY_TOGGLE_VISIBILITY => ['toggleVisibility', 128],

            TheliaEvents::CATEGORY_UPDATE_POSITION => ['updatePosition', 128],
            TheliaEvents::CATEGORY_UPDATE_SEO => ['updateSeo', 128],

            TheliaEvents::CATEGORY_ADD_CONTENT => ['addContent', 128],
            TheliaEvents::CATEGORY_REMOVE_CONTENT => ['removeContent', 128],

            TheliaEvents::VIEW_CHECK => ['viewCheck', 128],
            TheliaEvents::VIEW_CATEGORY_ID_NOT_VISIBLE => ['viewcategoryIdNotVisible', 128],
        ];
    }
}
