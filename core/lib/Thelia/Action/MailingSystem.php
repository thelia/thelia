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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\MailingSystem\MailingSystemEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ConfigQuery;

class MailingSystem extends BaseAction implements EventSubscriberInterface
{
    /**
     * @param MailingSystemEvent $event
     */
    public function update(MailingSystemEvent $event)
    {
        if ($event->getEnabled()) {
            ConfigQuery::enableSmtp();
        } else {
            ConfigQuery::disableSmtp();
        }
        ConfigQuery::setSmtpHost($event->getHost());
        ConfigQuery::setSmtpPort($event->getPort());
        ConfigQuery::setSmtpEncryption($event->getEncryption());
        ConfigQuery::setSmtpUsername($event->getUsername());
        ConfigQuery::setSmtpPassword($event->getPassword());
        ConfigQuery::setSmtpAuthMode($event->getAuthMode());
        ConfigQuery::setSmtpTimeout($event->getTimeout());
        ConfigQuery::setSmtpSourceIp($event->getSourceIp());
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::MAILING_SYSTEM_UPDATE => array("update", 128),
        );
    }
}
