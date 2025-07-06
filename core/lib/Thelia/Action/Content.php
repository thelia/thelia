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

use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Core\Event\Content\ContentAddFolderEvent;
use Thelia\Core\Event\Content\ContentCreateEvent;
use Thelia\Core\Event\Content\ContentDeleteEvent;
use Thelia\Core\Event\Content\ContentRemoveFolderEvent;
use Thelia\Core\Event\Content\ContentToggleVisibilityEvent;
use Thelia\Core\Event\Content\ContentUpdateEvent;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Core\Event\ViewCheckEvent;
use Thelia\Model\Content as ContentModel;
use Thelia\Model\ContentDocumentQuery;
use Thelia\Model\ContentFolder;
use Thelia\Model\ContentFolderQuery;
use Thelia\Model\ContentImageQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\Map\ContentTableMap;

/**
 * Class Content.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class Content extends BaseAction implements EventSubscriberInterface
{
    public function create(ContentCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $content = (new ContentModel())

            ->setVisible($event->getVisible())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->create($event->getDefaultFolder());

        $event->setContent($content);
    }

    /**
     * process update content.
     *
     * @throws PropelException
     * @throws \Exception
     */
    public function update(ContentUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $content = ContentQuery::create()->findPk($event->getContentId())) {
            $con = Propel::getWriteConnection(ContentTableMap::DATABASE_NAME);
            $con->beginTransaction();

            try {
                $content
                    ->setVisible($event->getVisible())
                    ->setLocale($event->getLocale())
                    ->setTitle($event->getTitle())
                    ->setDescription($event->getDescription())
                    ->setChapo($event->getChapo())
                    ->setPostscriptum($event->getPostscriptum())
                    ->save($con);

                $content->setDefaultFolder($event->getDefaultFolder());

                $event->setContent($content);
                $con->commit();
            } catch (PropelException $e) {
                $con->rollBack();

                throw $e;
            }
        }
    }

    /**
     * Change Content SEO.
     */
    public function updateSeo(UpdateSeoEvent $event, $eventName, EventDispatcherInterface $dispatcher): object
    {
        return $this->genericUpdateSeo(ContentQuery::create(), $event, $dispatcher);
    }

    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->genericUpdateDelegatePosition(
            ContentFolderQuery::create()
                ->filterByContentId($event->getObjectId())
                ->filterByFolderId($event->getReferrerId()),
            $event,
            $dispatcher,
        );
    }

    public function toggleVisibility(ContentToggleVisibilityEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $content = $event->getContent();

        $content

            ->setVisible(!$content->getVisible())
            ->save();

        $event->setContent($content);
    }

    public function delete(ContentDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $content = ContentQuery::create()->findPk($event->getContentId())) {
            $con = Propel::getWriteConnection(ContentTableMap::DATABASE_NAME);
            $con->beginTransaction();

            try {
                $fileList = ['images' => [], 'documentList' => []];

                $defaultFolderId = $content->getDefaultFolderId();

                // Get content's files to delete after content deletion
                $fileList['images']['list'] = ContentImageQuery::create()
                    ->findByContentId($event->getContentId());
                $fileList['images']['type'] = TheliaEvents::IMAGE_DELETE;

                $fileList['documentList']['list'] = ContentDocumentQuery::create()
                    ->findByContentId($event->getContentId());
                $fileList['documentList']['type'] = TheliaEvents::DOCUMENT_DELETE;

                // Delete content
                $content
                    ->delete($con);

                $event->setDefaultFolderId($defaultFolderId);
                $event->setContent($content);

                // Dispatch delete content's files event
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
     * associate a folder to a content if the association already does not exists.
     */
    public function addFolder(ContentAddFolderEvent $event): void
    {
        if (ContentFolderQuery::create()
            ->filterByContent($event->getContent())
            ->filterByFolderId($event->getFolderId())
            ->count() <= 0
        ) {
            $contentFolder = (new ContentFolder())
                ->setFolderId($event->getFolderId())
                ->setContent($event->getContent())
                ->setDefaultFolder(false);

            $contentFolder
                ->setPosition($contentFolder->getNextPosition())
                ->save();
        }
    }

    public function removeFolder(ContentRemoveFolderEvent $event): void
    {
        $contentFolder = ContentFolderQuery::create()
            ->filterByContent($event->getContent())
            ->filterByFolderId($event->getFolderId())
            ->findOne();

        if (null !== $contentFolder) {
            $contentFolder->delete();
        }
    }

    /**
     * Check if is a content view and if content_id is visible.
     */
    public function viewCheck(ViewCheckEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        if ('content' === $event->getView()) {
            $content = ContentQuery::create()
                ->filterById($event->getViewId())
                ->filterByVisible(1)
                ->count();

            if (0 === $content) {
                $dispatcher->dispatch($event, TheliaEvents::VIEW_CONTENT_ID_NOT_VISIBLE);
            }
        }
    }

    /**
     * @throws NotFoundHttpException
     */
    public function viewContentIdNotVisible(ViewCheckEvent $event): void
    {
        throw new NotFoundHttpException();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CONTENT_CREATE => ['create', 128],
            TheliaEvents::CONTENT_UPDATE => ['update', 128],
            TheliaEvents::CONTENT_DELETE => ['delete', 128],
            TheliaEvents::CONTENT_TOGGLE_VISIBILITY => ['toggleVisibility', 128],

            TheliaEvents::CONTENT_UPDATE_POSITION => ['updatePosition', 128],
            TheliaEvents::CONTENT_UPDATE_SEO => ['updateSeo', 128],

            TheliaEvents::CONTENT_ADD_FOLDER => ['addFolder', 128],
            TheliaEvents::CONTENT_REMOVE_FOLDER => ['removeFolder', 128],

            TheliaEvents::VIEW_CHECK => ['viewCheck', 128],
            TheliaEvents::VIEW_CONTENT_ID_NOT_VISIBLE => ['viewContentIdNotVisible', 128],
        ];
    }
}
