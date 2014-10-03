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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Content\ContentAddFolderEvent;
use Thelia\Core\Event\Content\ContentCreateEvent;
use Thelia\Core\Event\Content\ContentDeleteEvent;
use Thelia\Core\Event\Content\ContentRemoveFolderEvent;
use Thelia\Core\Event\Content\ContentToggleVisibilityEvent;
use Thelia\Core\Event\Content\ContentUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Model\ContentFolder;
use Thelia\Model\ContentFolderQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\Content as ContentModel;
use Thelia\Model\Map\ContentTableMap;

/**
 * Class Content
 * @package Thelia\Action
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class Content extends BaseAction implements EventSubscriberInterface
{
    public function create(ContentCreateEvent $event)
    {
        $content = new ContentModel();

        $content
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
     */
    public function update(ContentUpdateEvent $event)
    {
        if (null !== $content = ContentQuery::create()->findPk($event->getContentId())) {
            $con = Propel::getWriteConnection(ContentTableMap::DATABASE_NAME);
            $con->beginTransaction();

            $content->setDispatcher($event->getDispatcher());
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
     * @param \Thelia\Core\Event\UpdateSeoEvent $event
     *
     * @return mixed
     */
    public function updateSeo(UpdateSeoEvent $event)
    {
        return $this->genericUpdateSeo(ContentQuery::create(), $event);
    }

    public function updatePosition(UpdatePositionEvent $event)
    {
        $this->genericUpdatePosition(ContentQuery::create(), $event);
    }

    public function toggleVisibility(ContentToggleVisibilityEvent $event)
    {
        $content = $event->getContent();

        $content
            ->setDispatcher($event->getDispatcher())
            ->setVisible(!$content->getVisible())
            ->save();

        $event->setContent($content);
    }

    public function delete(ContentDeleteEvent $event)
    {
        if (null !== $content = ContentQuery::create()->findPk($event->getContentId())) {
            $defaultFolderId = $content->getDefaultFolderId();

            $content->setDispatcher($event->getDispatcher())
                ->delete();

            $event->setDefaultFolderId($defaultFolderId);
            $event->setContent($content);
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
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
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
