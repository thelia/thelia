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
use Thelia\Model\FolderDocumentQuery;
use Thelia\Model\FolderImageQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\Folder as FolderModel;
use Thelia\Model\Map\FolderTableMap;

/**
 * Class Folder
 * @package Thelia\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Folder extends BaseAction implements EventSubscriberInterface
{
    public function update(FolderUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $folder = FolderQuery::create()->findPk($event->getFolderId())) {
            $folder->setDispatcher($dispatcher);

            $folder
                ->setParent($event->getParent())
                ->setVisible($event->getVisible())
                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
                ->setChapo($event->getChapo())
                ->setPostscriptum($event->getPostscriptum())
                ->save();
            ;

            $event->setFolder($folder);
        }
    }

    /**
     * Change Folder SEO
     *
     * @param UpdateSeoEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     * @return Object
     */
    public function updateSeo(UpdateSeoEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        return $this->genericUpdateSeo(FolderQuery::create(), $event, $dispatcher);
    }

    public function delete(FolderDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
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
                $folder->setDispatcher($dispatcher)
                    ->delete($con);

                $event->setFolder($folder);

                // Dispatch delete folder's files event
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

    public function create(FolderCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $folder = new FolderModel();
        $folder->setDispatcher($dispatcher);

        $folder
            ->setParent($event->getParent())
            ->setVisible($event->getVisible())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->save();

        $event->setFolder($folder);
    }

    public function toggleVisibility(FolderToggleVisibilityEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $folder = $event->getFolder();

        $folder
            ->setDispatcher($dispatcher)
            ->setVisible(!$folder->getVisible())
            ->save();

        $event->setFolder($folder);
    }

    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $folder = FolderQuery::create()->findPk($event->getObjectId())) {
            $folder->setDispatcher($dispatcher);

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
     * Check if is a folder view and if folder_id is visible
     *
     * @param ViewCheckEvent $event
     * @param string $eventName
     * @param EventDispatcherInterface $dispatcher
     */
    public function viewCheck(ViewCheckEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if ($event->getView() == 'folder') {
            $folder = FolderQuery::create()
                ->filterById($event->getViewId())
                ->filterByVisible(1)
                ->count();

            if ($folder == 0) {
                $dispatcher->dispatch(TheliaEvents::VIEW_FOLDER_ID_NOT_VISIBLE, $event);
            }
        }
    }

    /**
     * @param ViewCheckEvent $event
     * @throws NotFoundHttpException
     */
    public function viewFolderIdNotVisible(ViewCheckEvent $event)
    {
        throw new NotFoundHttpException();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::FOLDER_CREATE            => array("create", 128),
            TheliaEvents::FOLDER_UPDATE            => array("update", 128),
            TheliaEvents::FOLDER_DELETE            => array("delete", 128),
            TheliaEvents::FOLDER_TOGGLE_VISIBILITY => array("toggleVisibility", 128),

            TheliaEvents::FOLDER_UPDATE_POSITION   => array("updatePosition", 128),
            TheliaEvents::FOLDER_UPDATE_SEO        => array('updateSeo', 128),

            TheliaEvents::VIEW_CHECK                    => array('viewCheck', 128),
            TheliaEvents::VIEW_FOLDER_ID_NOT_VISIBLE    => array('viewFolderIdNotVisible', 128),
        );
    }
}
