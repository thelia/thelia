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
use Thelia\Core\Event\Content\ContentAddFolderEvent;
use Thelia\Core\Event\Content\ContentCreateEvent;
use Thelia\Core\Event\Content\ContentDeleteEvent;
use Thelia\Core\Event\Content\ContentRemoveFolderEvent;
use Thelia\Core\Event\Content\ContentToggleVisibilityEvent;
use Thelia\Core\Event\Content\ContentUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Exception\UrlRewritingException;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\ContentFolder;
use Thelia\Model\ContentFolderQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\Content as ContentModel;

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
            $content->setDispatcher($this->getDispatcher());

            $content
                ->setVisible($event->getVisible())
                ->setLocale($event->getLocale())
                ->setTitle($event->getTitle())
                ->setDescription($event->getDescription())
                ->setChapo($event->getChapo())
                ->setPostscriptum($event->getPostscriptum())
                ->save()
            ;

            // Update the rewritten URL, if required
            try {
                $content->setRewrittenUrl($event->getLocale(), $event->getUrl());
            } catch(UrlRewritingException $e) {
                throw new FormValidationException($e->getMessage(), $e->getCode());
            }

            $content->updateDefaultFolder($event->getDefaultFolder());

            $event->setContent($content);
        }
    }

    public function updatePosition(UpdatePositionEvent $event)
    {
        if (null !== $content = ContentQuery::create()->findPk($event->getObjectId())) {
            $content->setDispatcher($this->getDispatcher());

            switch ($event->getMode()) {
                case UpdatePositionEvent::POSITION_ABSOLUTE:
                    $content->changeAbsolutePosition($event->getPosition());
                    break;
                case UpdatePositionEvent::POSITION_DOWN:
                    $content->movePositionDown();
                    break;
                case UpdatePositionEvent::POSITION_UP:
                    $content->movePositionUp();
                    break;
            }
        }
    }

    public function toggleVisibility(ContentToggleVisibilityEvent $event)
    {
        $content = $event->getContent();

        $content
            ->setDispatcher($this->getDispatcher())
            ->setVisible(!$content->getVisible())
            ->save();

        $event->setContent($content);

    }

    public function delete(ContentDeleteEvent $event)
    {
        if (null !== $content = ContentQuery::create()->findPk($event->getContentId())) {
            $defaultFolderId = $content->getDefaultFolderId();

            $content->setDispatcher($this->getDispatcher())
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
        if(ContentFolderQuery::create()
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

            TheliaEvents::CONTENT_ADD_FOLDER        => array('addFolder', 128),
            TheliaEvents::CONTENT_REMOVE_FOLDER     => array('removeFolder', 128),
        );
    }

}
