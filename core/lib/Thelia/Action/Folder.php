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

use Exception;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Core\Event\File\FileDeleteEvent;
use Thelia\Core\Event\Folder\FolderCreateEvent;
use Thelia\Core\Event\Folder\FolderDeleteEvent;
use Thelia\Core\Event\Folder\FolderToggleVisibilityEvent;
use Thelia\Core\Event\Folder\FolderUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Core\Event\ViewCheckEvent;
use Thelia\Model\Folder as FolderModel;
use Thelia\Model\FolderDocumentQuery;
use Thelia\Model\FolderImageQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\Map\FolderTableMap;

/**
 * Class Folder.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Folder extends BaseAction implements EventSubscriberInterface
{
    public function update(FolderUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $folder = FolderQuery::create()->findPk($event->getFolderId())) {
            $folder
                ->setParent($event->getParent())
                ->setVisible($event->getVisible())
                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
                ->setChapo($event->getChapo())
                ->setPostscriptum($event->getPostscriptum())
                ->save();

            $event->setFolder($folder);
        }
    }

    /**
     * Change Folder SEO.
     *
     * @return object
     */
    public function updateSeo(UpdateSeoEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        return $this->genericUpdateSeo(FolderQuery::create(), $event, $dispatcher);
    }

    public function delete(FolderDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $folder = FolderQuery::create()->findPk($event->getFolderId())) {
            $con = Propel::getWriteConnection(FolderTableMap::DATABASE_NAME);
            $con->beginTransaction();

            try {
                $fileList = ['images' => [], 'documentList' => []];

                // Get folder's files to delete after folder deletion
                $fileList['images']['list'] = FolderImageQuery::create()
                    ->findByFolderId($event->getFolderId());
                $fileList['images']['type'] = TheliaEvents::IMAGE_DELETE;

                $fileList['documentList']['list'] = FolderDocumentQuery::create()
                    ->findByFolderId($event->getFolderId());
                $fileList['documentList']['type'] = TheliaEvents::DOCUMENT_DELETE;

                // Delete folder
                $folder
                    ->delete($con);

                $event->setFolder($folder);

                // Dispatch delete folder's files event
                foreach ($fileList as $fileTypeList) {
                    foreach ($fileTypeList['list'] as $fileToDelete) {
                        $fileDeleteEvent = new FileDeleteEvent($fileToDelete);
                        $dispatcher->dispatch($fileDeleteEvent, $fileTypeList['type']);
                    }
                }

                $con->commit();
            } catch (Exception $e) {
                $con->rollback();
                throw $e;
            }
        }
    }

    public function create(FolderCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $folder = new FolderModel();

        $folder
            ->setParent($event->getParent())
            ->setVisible($event->getVisible())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->save();

        $event->setFolder($folder);
    }

    public function toggleVisibility(FolderToggleVisibilityEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $folder = $event->getFolder();

        $folder

            ->setVisible(!$folder->getVisible())
            ->save();

        $event->setFolder($folder);
    }

    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if (null !== $folder = FolderQuery::create()->findPk($event->getObjectId())) {
            switch ($event->getMode()) {
                case UpdatePositionEvent::POSITION_ABSOLUTE:
                    $folder->changeAbsolutePosition($event->getPosition());
                    break;
                case UpdatePositionEvent::POSITION_DOWN:
                    $folder->movePositionDown();
                    break;
                case UpdatePositionEvent::POSITION_UP:
                    $folder->movePositionUp();
                    break;
            }
        }
    }

    /**
     * Check if is a folder view and if folder_id is visible.
     *
     * @param string $eventName
     */
    public function viewCheck(ViewCheckEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        if ($event->getView() == 'folder') {
            $folder = FolderQuery::create()
                ->filterById($event->getViewId())
                ->filterByVisible(1)
                ->count();

            if ($folder == 0) {
                $dispatcher->dispatch($event, TheliaEvents::VIEW_FOLDER_ID_NOT_VISIBLE);
            }
        }
    }

    /**
     * @throws NotFoundHttpException
     */
    public function viewFolderIdNotVisible(ViewCheckEvent $event): void
    {
        throw new NotFoundHttpException();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::FOLDER_CREATE => ['create', 128],
            TheliaEvents::FOLDER_UPDATE => ['update', 128],
            TheliaEvents::FOLDER_DELETE => ['delete', 128],
            TheliaEvents::FOLDER_TOGGLE_VISIBILITY => ['toggleVisibility', 128],

            TheliaEvents::FOLDER_UPDATE_POSITION => ['updatePosition', 128],
            TheliaEvents::FOLDER_UPDATE_SEO => ['updateSeo', 128],

            TheliaEvents::VIEW_CHECK => ['viewCheck', 128],
            TheliaEvents::VIEW_FOLDER_ID_NOT_VISIBLE => ['viewFolderIdNotVisible', 128],
        ];
    }
}
