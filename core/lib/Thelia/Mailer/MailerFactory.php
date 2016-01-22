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
 * @author Manuel Raynaud <manu@raynaud.io>
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
        $smtpTransporter = \Swift_SmtpTransport::newInstance(ConfigQuery::getSmtpHost(), ConfigQuery::getSmtpPort());

        if (ConfigQuery::getSmtpEncryption()) {
            $smtpTransporter->setEncryption(ConfigQuery::getSmtpEncryption());
        }
        if (ConfigQuery::getSmtpUsername()) {
            $smtpTransporter->setUsername(ConfigQuery::getSmtpUsername());
        }
        if (ConfigQuery::getSmtpPassword()) {
            $smtpTransporter->setPassword(ConfigQuery::getSmtpPassword());
        }
        if (ConfigQuery::getSmtpAuthMode()) {
            $smtpTransporter->setAuthMode(ConfigQuery::getSmtpAuthMode());
        }
        if (ConfigQuery::getSmtpTimeout()) {
            $smtpTransporter->setTimeout(ConfigQuery::getSmtpTimeout());
        }
        if (ConfigQuery::getSmtpSourceIp()) {
            $smtpTransporter->setSourceIp(ConfigQuery::getSmtpSourceIp());
        }

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
            [ConfigQuery::getStoreEmail() => $storeName],
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
     * @param string $locale            If null, the default store locale is used.
     * @param array  $cc                Cc addresses. An array of (email-address => name) [optional]
     * @param array  $bcc               Bcc addresses. An array of (email-address => name) [optional]
     */
    public function sendEmailMessage($messageCode, $from, $to, $messageParameters = [], $locale = null, $cc = [], $bcc = [])
    {
        $store_email = ConfigQuery::getStoreEmail();

        if (! empty($store_email)) {
            if (! empty($to)) {
                $instance = $this->createEmailMessage($messageCode, $from, $to, $messageParameters, $locale, $cc, $bcc);

                $sentCount = $this->send($instance, $failedRecipients);

                if ($sentCount == 0) {
                    Tlog::getInstance()->addError(
                        Translator::getInstance()->trans(
                            "Failed to send message %code. Failed recipients: %failed_addresses",
                            [
                                '%code' => $messageCode,
                                '%failed_addresses' => is_array($failedRecipients) ? implode(
                                    ',',
                                    $failedRecipients
                                ) : 'none'
                            ]
                        )
                    );
                }
            } else {
                Tlog::getInstance()->addWarning("Message $messageCode not sent: recipient list is empty.");
            }
        } else {
            Tlog::getInstance()->addError("Can't send email message $messageCode: store email address is not defined.");
        }
    }

    /**
     * Create a SwiftMessage instance from a given message code.
     *
     * @param  string $messageCode
     * @param  array  $from              From addresses. An array of (name => email-address)
     * @param  array  $to                To addresses. An array of (name => email-address)
     * @param  array  $messageParameters an array of (name => value) parameters that will be available in the message.
     * @param  string $locale            If null, the default store locale is used.
     * @param  array  $cc                Cc addresses. An array of (email-address => name) [optional]
     * @param  array  $bcc               Bcc addresses. An array of (email-address => name) [optional]
     *
     * @return \Swift_Message the generated and built message.
     */
    public function createEmailMessage($messageCode, $from, $to, $messageParameters = [], $locale = null, $cc = [], $bcc = [])
    {
        if (null !== $message = MessageQuery::getFromName($messageCode)) {
            if ($locale == null) {
                $locale = Lang::getDefaultLanguage()->getLocale();
            }

            $message->setLocale($locale);

            // Assign parameters
            foreach ($messageParameters as $name => $value) {
                $this->parser->assign($name, $value);
            }

            $this->parser->assign('locale', $locale);

            $instance = \Swift_Message::newInstance();

            // Add from addresses
            foreach ($from as $address => $name) {
                $instance->addFrom($address, $name);
            }

            // Add to addresses
            foreach ($to as $address => $name) {
                $instance->addTo($address, $name);
            }

            // Add cc addresses
            foreach ($cc as $address => $name) {
                $instance->addCc($address, $name);
            }
            
            // Add bcc addresses
            foreach ($bcc as $address => $name) {
                $instance->addBcc($address, $name);
            }
            
            $message->buildMessage($this->parser, $instance);

            return $instance;
        }

        throw new \RuntimeException(
            Translator::getInstance()->trans(
                "Failed to load message with code '%code%', propably because it does'nt exists.",
                [ '%code%' => $messageCode ]
            )
        );
    }
}
