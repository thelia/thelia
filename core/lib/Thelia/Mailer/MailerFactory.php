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
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
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
        $this->parser    = $parser;

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
        return $this->swiftMailer->send($message, $failedRecipients);
    }

    public function getSwiftMailer()
    {
        return $this->swiftMailer;
    }

    /**
     * Send a message to the customer.
     *
     * @param string   $messageCode
     * @param Customer $customer
     * @param array    $messageParameters an array of (name => value) parameters that will be available in the message.
     */
    public function sendEmailToCustomer($messageCode, $customer, $messageParameters = [])
    {
        // Always add the customer ID to the parameters
        $messageParameters['customer_id'] = $customer->getId();

        $this->sendEmailMessage(
            $messageCode,
            [ ConfigQuery::getStoreEmail() => ConfigQuery::getStoreName() ],
            [ $customer->getEmail() => $customer->getFirstname()." ".$customer->getLastname() ],
            $messageParameters,
            $customer->getCustomerLang()->getLocale()
        );
     }

    /**
     * Send a message to the shop managers.
     *
     * @param string $messageCode
     * @param array  $messageParameters an array of (name => value) parameters that will be available in the message.
     */
    public function sendEmailToShopManagers($messageCode, $messageParameters = [])
    {

        $storeName = ConfigQuery::getStoreName();

        // Build the list of email recipients
        $recipients = ConfigQuery::getNotificationEmailsList();

        $to = [];

        foreach ($recipients as $recipient) {
            $to[$recipient] = $storeName;
        }

        $this->sendEmailMessage(
            $messageCode,
            [ $storeName => ConfigQuery::getStoreEmail() ],
            $to,
            $messageParameters
        );
     }

    /**
     * Send a message to the customer.
     *
     * @param string $messageCode
     * @param array  $from              From addresses. An array of (email-address => name)
     * @param array  $to                To addresses. An array of (email-address => name)
     * @param array  $messageParameters an array of (name => value) parameters that will be available in the message.
     * @param string $locale.           If null, the default store locale is used.
     */
    public function sendEmailMessage($messageCode, $from, $to, $messageParameters = [], $locale = null)
    {
        $store_email = ConfigQuery::getStoreEmail();

        if (! empty($store_email)) {
            $message = MessageQuery::getFromName($messageCode);

            if ($locale == null) {
                $locale = Lang::getDefaultLanguage()->getLocale();
            }

            $message->setLocale($locale);

            // Assign parameters
            foreach ($messageParameters as $name => $value) {
                $this->parser->assign($name, $value);
            }

            $instance = \Swift_Message::newInstance();

            // Add from addresses
            foreach($from as $address => $name)
                $instance->addFrom($address, $name);

            // Add to addresses
            foreach($to as $address => $name)
                $instance->addTo($address, $name);

            $message->buildMessage($this->parser, $instance);

            $sentCount = $this->send($instance, $failedRecipients);

            if ($sentCount == 0) {
                Tlog::getInstance()->addError(
                    Translator::getInstance()->trans(
                        "Failed to send message %code. Failed recipients: %failed_addresses",
                        [
                            '%code'       => $messageCode,
                            '%failed_addresses' => is_array($failedRecipients) ? implode(',', $failedRecipients) : 'none'
                        ]
                    ));
            }
        } else {
            Tlog::getInstance()->addError("Can't send email message $messageCode: store email address is not defined.");
        }
    }

    /**
     * Send a message to the customer.
     *
     * @param string $messageCode
     * @param array  $from              From addresses. An array of (name => email-address)
     * @param array  $to                To addresses. An array of (name => email-address)
     * @param array  $messageParameters an array of (name => value) parameters that will be available in the message.
     * @param string locale. If null, the default store locale is used.
     */
    public function sendEmail($from, $to, $subject, $body)
    {
        $store_email = ConfigQuery::getStoreEmail();

        if (! empty($store_email)) {
            $message = MessageQuery::getFromName($messageCode);

            if ($locale == null) {
                $locale = Lang::getDefaultLanguage()->getLocale();
            }

            $message->setLocale($locale);

            // Assign parameters
            foreach ($messageParameters as $name => $value) {
                $this->parser->assign($name, $value);
            }

            $instance = \Swift_Message::newInstance();

            // Add from addresses
            foreach($from as $name => $address)
                $instance->addFrom($address, $name);

            // Add to addresses
            foreach($to as $name => $address)
                $instance->addTo($address, $name);

            $message->buildMessage($this->parser, $instance);

            $sentCount = $this->send($instance, $failedRecipients);

            if ($sentCount == 0) {
                Tlog::getInstance()->addError(
                    Translator::getInstance()->trans(
                        "Failed to send message %code. Failed recipients: %failed_addresses",
                        [
                            '%code'       => $messageCode,
                            '%failed_addresses' => is_array($failedRecipients) ? implode(',', $failedRecipients) : 'none'
                        ]
                    ));
            }
        } else {
            Tlog::getInstance()->addError("Can't send email message $messageCode: store email address is not defined.");
        }
    }

}
