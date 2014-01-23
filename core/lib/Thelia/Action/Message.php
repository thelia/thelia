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

use Thelia\Model\MessageQuery;
use Thelia\Model\Message as MessageModel;

use Thelia\Core\Event\TheliaEvents;

use Thelia\Core\Event\Message\MessageUpdateEvent;
use Thelia\Core\Event\Message\MessageCreateEvent;
use Thelia\Core\Event\Message\MessageDeleteEvent;

class Message extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new messageuration entry
     *
     * @param \Thelia\Core\Event\Message\MessageCreateEvent $event
     */
    public function create(MessageCreateEvent $event)
    {
        $message = new MessageModel();

        $message
            ->setDispatcher($this->getDispatcher())

            ->setName($event->getMessageName())

            ->setLocale($event->getLocale())
            ->setTitle($event->getTitle())

            ->setSecured($event->getSecured())

            ->save()
        ;

        $event->setMessage($message);
    }

    /**
     * Change a message
     *
     * @param \Thelia\Core\Event\Message\MessageUpdateEvent $event
     */
    public function modify(MessageUpdateEvent $event)
    {

        if (null !== $message = MessageQuery::create()->findPk($event->getMessageId())) {

            $message
                ->setDispatcher($this->getDispatcher())

                ->setName($event->getMessageName())
                ->setSecured($event->getSecured())

                ->setLocale($event->getLocale())

                ->setTitle($event->getTitle())
                ->setSubject($event->getSubject())

                ->setHtmlMessage($event->getHtmlMessage())
                ->setTextMessage($event->getTextMessage())

                ->setHtmlLayoutFileName($event->getHtmlLayoutFileName())
                ->setHtmlTemplateFileName($event->getHtmlTemplateFileName())
                ->setTextLayoutFileName($event->getTextLayoutFileName())
                ->setTextTemplateFileName($event->getTextTemplateFileName())

                ->save();

            $event->setMessage($message);
        }
    }

    /**
     * Delete a messageuration entry
     *
     * @param \Thelia\Core\Event\Message\MessageDeleteEvent $event
     */
    public function delete(MessageDeleteEvent $event)
    {

        if (null !== ($message = MessageQuery::create()->findPk($event->getMessageId()))) {

            $message
                ->setDispatcher($this->getDispatcher())
                ->delete()
            ;

            $event->setMessage($message);
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::MESSAGE_CREATE   => array("create", 128),
            TheliaEvents::MESSAGE_UPDATE   => array("modify", 128),
            TheliaEvents::MESSAGE_DELETE   => array("delete", 128),
        );
    }
}
