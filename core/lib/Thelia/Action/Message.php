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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Message\MessageCreateEvent;
use Thelia\Core\Event\Message\MessageDeleteEvent;
use Thelia\Core\Event\Message\MessageUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Message as MessageModel;
use Thelia\Model\MessageQuery;

class Message extends BaseAction implements EventSubscriberInterface
{
    /**
     * Create a new messageuration entry
     *
     * @param $eventName
     */
    public function create(MessageCreateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $message = new MessageModel();

        $message

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
     * @param $eventName
     */
    public function modify(MessageUpdateEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== $message = MessageQuery::create()->findPk($event->getMessageId())) {
            $message

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
     * @param $eventName
     */
    public function delete(MessageDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if (null !== ($message = MessageQuery::create()->findPk($event->getMessageId()))) {
            $message

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
        return [
            TheliaEvents::MESSAGE_CREATE   => ["create", 128],
            TheliaEvents::MESSAGE_UPDATE   => ["modify", 128],
            TheliaEvents::MESSAGE_DELETE   => ["delete", 128],
        ];
    }
}
