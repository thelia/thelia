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

use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
use Thelia\Model\ContentDocumentQuery;
use Thelia\Model\ContentFolder;
use Thelia\Model\ContentFolderQuery;
use Thelia\Model\ContentImageQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\Content as ContentModel;
use Thelia\Model\Map\ContentTableMap;

/**
 * Class Content
 * @package Thelia\Action
 * @author manuel raynaud <manu@raynaud.io>
 */
class Content extends BaseAction implements EventSubscriberInterface
{
    public function create(ContentCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $content = (new ContentModel)
            ->setDispatcher($dispatcher)
            ->setVisible($event->getVisible())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->create($event->getDefaultFolder())
        ;

        $event->setContent($content);
    }

    /**
     * process update content
     *
     * @param ContentUpdateEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     * @throws PropelException
     * @throws \Exception
     */
    public function update(ContentUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $content = ContentQuery::create()->findPk($event->getContentId())) {
            $con = Propel::getWriteConnection(ContentTableMap::DATABASE_NAME);
            $con->beginTransaction();

            $content->setDispatcher($dispatcher);
            try {
                $content
                    ->setVisible($event->getVisible())
                    ->setLocale($event->getLocale())
                    ->setTitle($event->getTitle())
                    ->setDescription($event->getDescription())
                    ->setChapo($event->getChapo())
                    ->setPostscriptum($event->getPostscriptum())
                    ->save($con)
                ;

                $content->updateDefaultFolder($event->getDefaultFolder());

                $event->setContent($content);
                $con->commit();
            } catch (PropelException $e) {
                $con->rollBack();
                throw $e;
            }
        }
    }

    /**
     * Change Content SEO
     *
     * @param UpdateSeoEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     * @return Object
     */
    public function updateSeo(UpdateSeoEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        return $this->genericUpdateSeo(ContentQuery::create(), $event, $dispatcher);
    }

    public function updatePosition(UpdatePositionEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $this->genericUpdatePosition(ContentQuery::create(), $event, $dispatcher);
    }

    public function toggleVisibility(ContentToggleVisibilityEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $content = $event->getContent();

        $content
            ->setDispatcher($dispatcher)
            ->setVisible(!$content->getVisible())
            ->save();

        $event->setContent($content);
    }

    public function delete(ContentDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
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
                $content->setDispatcher($dispatcher)
                    ->delete($con);

                $event->setDefaultFolderId($defaultFolderId);
                $event->setContent($content);

                // Dispatch delete content's files event
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
     *
     * associate a folder to a content if the association already does not exists
     *
     * @param ContentAddFolderEvent $event
     */
    public function addFolder(ContentAddFolderEvent $event)
    {
        if (ContentFolderQuery::create()
            ->filterByContent($event->getContent())
            ->filterByFolderId($event->getFolderId())
            ->count() <= 0
        ) {
            $contentFolder = new ContentFolder();

            $contentFolder
                ->setFolderId($event->getFolderId())
                ->setContent($event->getContent())
                ->setDefaultFolder(false)
                ->save();
        }
    }

    public function removeFolder(ContentRemoveFolderEvent $event)
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
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::CONTENT_CREATE           => array('create', 128),
            TheliaEvents::CONTENT_UPDATE            => array('update', 128),
            TheliaEvents::CONTENT_DELETE            => array('delete', 128),
            TheliaEvents::CONTENT_TOGGLE_VISIBILITY => array('toggleVisibility', 128),

            TheliaEvents::CONTENT_UPDATE_POSITION   => array('updatePosition', 128),
            TheliaEvents::CONTENT_UPDATE_SEO        => array('updateSeo', 128),

            TheliaEvents::CONTENT_ADD_FOLDER        => array('addFolder', 128),
            TheliaEvents::CONTENT_REMOVE_FOLDER     => array('removeFolder', 128),
        );
    }
}
