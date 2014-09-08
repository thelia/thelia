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

namespace Thelia\Mailer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\MailTransporterEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\ParserInterface;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\MessageQuery;

/**
 * Class MailerFactory
 * @package Thelia\Mailer
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class MailerFactory
{
    /**
     * @var \Swift_Mailer
     */
    protected $swiftMailer;

    protected $dispatcher;
    protected $parser;

    public function __construct(EventDispatcherInterface $dispatcher, ParserInterface $parser)
    {

        $this->dispatcher = $dispatcher;
        $this->$parser    = $parser;

        $transporterEvent = new MailTransporterEvent();
        $this->dispatcher->dispatch(TheliaEvents::MAILTRANSPORTER_CONFIG, $transporterEvent);

        if ($transporterEvent->hasTransporter()) {
            $transporter = $transporterEvent->getTransporter();
        } else {
            if (ConfigQuery::isSmtpEnable()) {
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
        $smtpTransporter->setHost(Configquery::getSmtpHost())
            ->setPort(ConfigQuery::getSmtpPort())
            ->setEncryption(ConfigQuery::getSmtpEncryption())
            ->setUsername(ConfigQuery::getSmtpUsername())
            ->setPassword(ConfigQuery::getSmtpPassword())
            ->setAuthMode(ConfigQuery::getSmtpAuthMode())
            ->setTimeout(ConfigQuery::getSmtpTimeout())
            ->setSourceIp(ConfigQuery::getSmtpSourceIp())
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


    /**
     * Send a message to the customer.
     *
     * @param string $messageCode
     * @param Customer $customer
     * @param array $messageParameters an array of (name => value) parameters that will be available in the message.
     */
    public function sendEmailToCustomer($messageCode, $customer, $messageParameters = [])
    {
        $store_email = ConfigQuery::getStoreEmail();

        if (! empty($store_email)) {
            $message = MessageQuery::getFromName($messageCode);

            $locale = $customer->getCustomerLang()->getLocale();

            $message->setLocale($locale);

            foreach($messageParameters as $name => $value) {
                $this->parser->assign($name, $value);
            }

            $this->parser->assign('customer_id', $customer->getId());

            $instance = \Swift_Message::newInstance()
                ->addTo($customer->getEmail(), $customer->getFirstname()." ".$customer->getLastname())
                ->addFrom($store_email, ConfigQuery::getStoreName())
            ;

            $message->buildMessage($this->parser, $instance);

            $this->send($instance);
        }
        else {
            Tlog::getInstance()->addError("Can't send email message $messageCode: store email address is not defined.");
        }
    }

}
