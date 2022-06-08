<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Mailer;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\MessageQuery;

/**
 * Class MailerFactory.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class MailerFactory
{
    public function __construct(private ParserInterface $parser, private MailerInterface $mailer)
    {
    }

    public function send(Email $message): void
    {
        $this->mailer->send($message);
    }

    /**
     * Send a message to the customer.
     *
     * @param string   $messageCode
     * @param Customer $customer
     * @param array    $messageParameters an array of (name => value) parameters that will be available in the message
     */
    public function sendEmailToCustomer($messageCode, $customer, $messageParameters = []): void
    {
        // Always add the customer ID to the parameters
        $messageParameters['customer_id'] = $customer->getId();

        $this->sendEmailMessage(
            $messageCode,
            [ConfigQuery::getStoreEmail() => ConfigQuery::getStoreName()],
            [$customer->getEmail() => $customer->getFirstname().' '.$customer->getLastname()],
            $messageParameters,
            $customer->getCustomerLang()->getLocale()
        );
    }

    /**
     * Send a message to the shop managers.
     *
     * @param string $messageCode
     * @param array  $messageParameters an array of (name => value) parameters that will be available in the message
     * @param array  $replyTo           Reply to addresses. An array of (email-address => name) [optional]
     */
    public function sendEmailToShopManagers($messageCode, $messageParameters = [], $replyTo = []): void
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
            $messageParameters,
            null,
            [],
            [],
            $replyTo
        );
    }

    /**
     * Send a message to the customer.
     *
     * @param string $messageCode
     * @param array  $from              From addresses. An array of (email-address => name)
     * @param array  $to                To addresses. An array of (email-address => name)
     * @param array  $messageParameters an array of (name => value) parameters that will be available in the message
     * @param string $locale            if null, the default store locale is used
     * @param array  $cc                Cc addresses. An array of (email-address => name) [optional]
     * @param array  $bcc               Bcc addresses. An array of (email-address => name) [optional]
     * @param array  $replyTo           Reply to addresses. An array of (email-address => name) [optional]
     */
    public function sendEmailMessage($messageCode, $from, $to, $messageParameters = [], $locale = null, $cc = [], $bcc = [], $replyTo = []): void
    {
        $storeEmail = ConfigQuery::getStoreEmail();

        if (!empty($storeEmail)) {
            if (!empty($to)) {
                try {
                    $instance = $this->createEmailMessage($messageCode, $from, $to, $messageParameters, $locale, $cc, $bcc, $replyTo);

                    $this->send($instance);
                } catch (\Exception $ex) {
                    Tlog::getInstance()->addError(
                        "Error while sending email message $messageCode: ".$ex->getMessage()
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
     * @param string $messageCode
     * @param array  $from              From addresses. An array of (email-address => name)
     * @param array  $to                To addresses. An array of (email-address => name)
     * @param array  $messageParameters an array of (name => value) parameters that will be available in the message
     * @param string $locale            if null, the default store locale is used
     * @param array  $cc                Cc addresses. An array of (email-address => name) [optional]
     * @param array  $bcc               Bcc addresses. An array of (email-address => name) [optional]
     * @param array  $replyTo           Reply to addresses. An array of (email-address => name) [optional]
     *
     * @throws \Exception
     */
    public function createEmailMessage($messageCode, $from, $to, $messageParameters = [], $locale = null, $cc = [], $bcc = [], $replyTo = [])
    {
        $message = MessageQuery::getFromName($messageCode);
        if (null == $message) {
            throw new \RuntimeException(
                Translator::getInstance()->trans(
                    "Failed to load message with code '%code%', propably because it does'nt exists.",
                    ['%code%' => $messageCode]
                )
            );
        }

        if ($locale === null) {
            $locale = Lang::getDefaultLanguage()->getLocale();
        }

        $message->setLocale($locale);

        // Assign parameters
        foreach ($messageParameters as $name => $value) {
            $this->parser->assign($name, $value);
        }

        // As the parser uses the lang stored in the session, temporarly set the required language into the session.
        // This is required in the back office when sending emails to customers, that may use a different locale than
        // the current one.
        $session = $this->parser->getRequest()->getSession();

        $currentLang = $session->getLang();

        if (null !== $requiredLang = LangQuery::create()->findOneByLocale($locale)) {
            $session->setLang($requiredLang);
        }

        $email = (new Email());

        $this->setupMessageHeaders($email, $from, $to, $cc, $bcc, $replyTo);

        $message->buildMessage($this->parser, $email);

        $session->setLang($currentLang);

        return $email;
    }

    /**
     * Create a SwiftMessage instance from text.
     *
     * @param array  $from     From addresses. An array of (email-address => name)
     * @param array  $to       To addresses. An array of (email-address => name)
     * @param string $subject  the message subject
     * @param string $htmlBody the HTML message body, or null
     * @param string $textBody the text message body, or null
     * @param array  $cc       Cc addresses. An array of (email-address => name) [optional]
     * @param array  $bcc      Bcc addresses. An array of (email-address => name) [optional]
     * @param array  $replyTo  Reply to addresses. An array of (email-address => name) [optional]
     *
     * @return Email the generated and built message
     */
    public function createSimpleEmailMessage($from, $to, $subject, $htmlBody, $textBody, $cc = [], $bcc = [], $replyTo = [])
    {
        $email = (new Email());

        $this->setupMessageHeaders($email, $from, $to, $cc, $bcc, $replyTo);

        $email->subject($subject);
        $email->subject($subject);
        $email->text($htmlBody);
        $email->html($textBody);

        return $email;
    }

    /**
     * @param array  $from     From addresses. An array of (email-address => name)
     * @param array  $to       To addresses. An array of (email-address => name)
     * @param string $subject  the message subject
     * @param string $htmlBody the HTML message body, or null
     * @param string $textBody the text message body, or null
     * @param array  $cc       Cc addresses. An array of (email-address => name) [optional]
     * @param array  $bcc      Bcc addresses. An array of (email-address => name) [optional]
     * @param array  $replyTo  Reply to addresses. An array of (email-address => name) [optional]
     */
    public function sendSimpleEmailMessage($from, $to, $subject, $htmlBody, $textBody, $cc = [], $bcc = [], $replyTo = []): void
    {
        $email = $this->createSimpleEmailMessage($from, $to, $subject, $htmlBody, $textBody, $cc, $bcc, $replyTo);

        $this->send($email);
    }

    /**
     * @param Email $email
     * @param array $from    From addresses. An array of (email-address => name)
     * @param array $to      To addresses. An array of (email-address => name)
     * @param array $cc      Cc addresses. An array of (email-address => name) [optional]
     * @param array $bcc     Bcc addresses. An array of (email-address => name) [optional]
     * @param array $replyTo Reply to addresses. An array of (email-address => name) [optional]
     */
    protected function setupMessageHeaders($email, $from, $to, $cc = [], $bcc = [], $replyTo = []): void
    {
        // Add from addresses
        foreach ($from as $address => $name) {
            $email->addFrom(new Address($address, $name));
        }

        // Add to addresses
        foreach ($to as $address => $name) {
            $email->addTo(new Address($address, $name));
        }

        // Add cc addresses
        foreach ($cc as $address => $name) {
            $email->addCc(new Address($address, $name));
        }

        // Add bcc addresses
        foreach ($bcc as $address => $name) {
            $email->addBcc(new Address($address, $name));
        }

        // Add reply to addresses
        foreach ($replyTo as $address => $name) {
            $email->addReplyTo(new Address($address, $name));
        }
    }
}
