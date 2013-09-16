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

namespace Thelia\Mailer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\MailTransporterEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ConfigQuery;


/**
 * Class MailerFactory
 * @package Thelia\Mailer
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class MailerFactory {
    /**
     * @var \Swift_Mailer
     */
    protected $swiftMailer;

    protected $dispatcher;

    public function _construct(EventDispatcherInterface $dispatcher)
    {

        $this->dispatcher = $dispatcher;

        $transporterEvent = new MailTransporterEvent();
        $this->dispatcher->dispatch(TheliaEvents::MAILTRANSPORTER_CONFIG, $transporterEvent);

        if($transporterEvent->hasTransporter()) {
            $transporter = $transporterEvent->getTransporter();
        } else {
            if (ConfigQuery::read("smtp.enabled")) {
                $transporter = $this->configureSmtp();
            } else {
                $transporter = \Swift_MailTransport::newInstance();
            }
        }

        $this->swiftMailer = new \Swift_Mailer($transporter);
    }

    private function configureSmtp()
    {
        $smtpTransporter = new \Swift_SmtpTransport();
        $smtpTransporter->setHost(Configquery::read('smtp.host', 'localhost'))
            ->setPort(ConfigQuery::read('smtp.host'))
            ->setEncryption(ConfigQuery::read('smtp.encryption'))
            ->setUsername(ConfigQuery::read('smtp.username'))
            ->setPassword(ConfigQuery::read('smtp.password'))
            ->setAuthMode(ConfigQuery::read('smtp.authmode'))
            ->setTimeout(ConfigQuery::read('smtp.timeout', 30))
            ->setSourceIp(ConfigQuery::read('smtp.sourceip'))
        ;
        return $smtpTransporter;
    }

    public function send(\Swift_Mime_Message $message, &$failedRecipients = null)
    {
        $this->swiftMailer->send($message, $failedRecipients);
    }

    public function getSwiftMailer()
    {
        return $this->swiftMailer;
    }


}