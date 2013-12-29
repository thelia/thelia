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
        if($event->getEnabled()) {
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
            TheliaEvents::MAILING_SYSTEM_UPDATE                        => array("update", 128),
        );
    }
}
