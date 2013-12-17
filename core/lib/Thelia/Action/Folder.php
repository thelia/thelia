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
use Thelia\Core\Event\Folder\FolderCreateEvent;
use Thelia\Core\Event\Folder\FolderDeleteEvent;
use Thelia\Core\Event\Folder\FolderToggleVisibilityEvent;
use Thelia\Core\Event\Folder\FolderUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Model\FolderQuery;
use Thelia\Model\Folder as FolderModel;

/**
 * Class Folder
 * @package Thelia\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Folder extends BaseAction implements EventSubscriberInterface
{
    public function update(FolderUpdateEvent $event)
    {

        if (null !== $folder = FolderQuery::create()->findPk($event->getFolderId())) {
            $folder->setDispatcher($this->getDispatcher());

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
     * @param \Thelia\Core\Event\UpdateSeoEvent $event
     *
     * @return mixed
     */
    public function updateSeo(UpdateSeoEvent $event)
    {
        return $this->genericUpdateSeo(FolderQuery::create(), $event);
    }

    public function delete(FolderDeleteEvent $event)
    {
        if (null !== $folder = FolderQuery::create()->findPk($event->getFolderId())) {
            $folder->setDispatcher($this->getDispatcher())
                ->delete();

            $event->setFolder($folder);
        }
    }

    /**
     * @param FolderCreateEvent $event
     */
    public function create(FolderCreateEvent $event)
    {
        $folder = new FolderModel();
        $folder->setDispatcher($this->getDispatcher());

        $folder
            ->setParent($event->getParent())
            ->setVisible($event->getVisible())
            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())
            ->save();

        $event->setFolder($folder);
    }

    public function toggleVisibility(FolderToggleVisibilityEvent $event)
    {
        $folder = $event->getFolder();

        $folder
            ->setDispatcher($this->getDispatcher())
            ->setVisible(!$folder->getVisible())
            ->save();

        $event->setFolder($folder);

    }

    public function updatePosition(UpdatePositionEvent $event)
    {
        if (null !== $folder = FolderQuery::create()->findPk($event->getObjectId())) {
            $folder->setDispatcher($this->getDispatcher());

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
            TheliaEvents::FOLDER_CREATE            => array("create", 128),
            TheliaEvents::FOLDER_UPDATE            => array("update", 128),
            TheliaEvents::FOLDER_DELETE            => array("delete", 128),
            TheliaEvents::FOLDER_TOGGLE_VISIBILITY => array("toggleVisibility", 128),

            TheliaEvents::FOLDER_UPDATE_POSITION   => array("updatePosition", 128),
            TheliaEvents::FOLDER_UPDATE_SEO        => array('updateSeo', 128)
        );
    }
}
