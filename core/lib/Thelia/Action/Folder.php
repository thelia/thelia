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
use Thelia\Core\Event\FolderUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\FolderQuery;


/**
 * Class Folder
 * @package Thelia\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Folder extends BaseAction implements EventSubscriberInterface {


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
        );
    }
}