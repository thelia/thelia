<?php

declare(strict_types=1);

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
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelperInterface;
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
    public function __construct(
        private readonly TemplateHelperInterface $templateHelper,
        private readonly ParserResolver $parserResolver,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function send(Email $message): void
    {
        $this->mailer->send($message);
    }

    /**
     * Send a message to the customer.
     *
     * @param array $messageParameters an array of (name => value) parameters that will be available in the message
     */
    public function sendEmailToCustomer(string $messageCode, Customer $customer, array $messageParameters = []): void
    {
        // Always add the customer ID to the parameters
        $messageParameters['customer_id'] = $customer->getId();

        $this->sendEmailMessage(
            $messageCode,
            [ConfigQuery::getStoreEmail() => ConfigQuery::getStoreName()],
            [$customer->getEmail() => $customer->getFirstname().' '.$customer->getLastname()],
            $messageParameters,
            $customer->getCustomerLang()->getLocale(),
        );
    }

    /**
     * Send a message to the shop managers.
     *
     * @param array $messageParameters an array of (name => value) parameters that will be available in the message
     * @param array $replyTo           Reply to addresses. An array of (email-address => name) [optional]
     */
    public function sendEmailToShopManagers(string $messageCode, array $messageParameters = [], array $replyTo = []): void
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
            $replyTo,
        );
    }

    /**
     * Send a message to the customer.
     *
     * @param array       $from              From addresses. An array of (email-address => name)
     * @param array       $to                To addresses. An array of (email-address => name)
     * @param array       $messageParameters an array of (name => value) parameters that will be available in the message
     * @param string|null $locale            if null, the default store locale is used
     * @param array       $cc                Cc addresses. An array of (email-address => name) [optional]
     * @param array       $bcc               Bcc addresses. An array of (email-address => name) [optional]
     * @param array       $replyTo           Reply to addresses. An array of (email-address => name) [optional]
     */
    public function sendEmailMessage(
        string $messageCode,
        array $from,
        array $to,
        array $messageParameters = [],
        ?string $locale = null,
        array $cc = [],
        array $bcc = [],
        array $replyTo = [],
    ): void {
        $storeEmail = ConfigQuery::getStoreEmail();

        if (empty($storeEmail)) {
            Tlog::getInstance()->addError(\sprintf("Can't send email message %s: store email address is not defined.", $messageCode));

            return;
        }
        if ([] === $to) {
            Tlog::getInstance()->addWarning(\sprintf('Message %s not sent: recipient list is empty.', $messageCode));

            return;
        }

        try {
            $instance = $this->createEmailMessage($messageCode, $from, $to, $messageParameters, $locale, $cc, $bcc, $replyTo);

            $this->send($instance);
        } catch (\Exception $ex) {
            Tlog::getInstance()->addError(
                \sprintf('Error while sending email message %s: ', $messageCode).$ex->getMessage(),
            );
        }
    }

    /**
     * Create a SwiftMessage instance from a given message code.
     *
     * @param array       $from              From addresses. An array of (email-address => name)
     * @param array       $to                To addresses. An array of (email-address => name)
     * @param array       $messageParameters an array of (name => value) parameters that will be available in the message
     * @param string|null $locale            if null, the default store locale is used
     * @param array       $cc                Cc addresses. An array of (email-address => name) [optional]
     * @param array       $bcc               Bcc addresses. An array of (email-address => name) [optional]
     * @param array       $replyTo           Reply to addresses. An array of (email-address => name) [optional]
     *
     * @throws \Exception
     */
    public function createEmailMessage(string $messageCode, array $from, array $to, array $messageParameters = [], ?string $locale = null, array $cc = [], array $bcc = [], array $replyTo = []): Email
    {
        $message = MessageQuery::getFromName($messageCode);

        if (null === $locale) {
            $locale = Lang::getDefaultLanguage()->getLocale();
        }

        $message->setLocale($locale);
        $parser = $this->getParser($messageCode);
        // Assign parameters
        foreach ($messageParameters as $name => $value) {
            $parser->assign($name, $value);
        }

        // As the parser uses the lang stored in the session, temporarly set the required language into the session.
        // This is required in the back office when sending emails to customers, that may use a different locale than
        // the current one.
        $session = $parser->getRequest()->getSession();

        $currentLang = $session->getLang();

        if (null !== $requiredLang = LangQuery::create()->findOneByLocale($locale)) {
            $session->setLang($requiredLang);
        }

        $email = (new Email());

        $this->setupMessageHeaders($email, $from, $to, $cc, $bcc, $replyTo);

        $message->buildMessage($parser, $email);

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
    public function createSimpleEmailMessage(array $from, array $to, string $subject, string $htmlBody, string $textBody, array $cc = [], array $bcc = [], array $replyTo = []): Email
    {
        $email = (new Email());

        $this->setupMessageHeaders($email, $from, $to, $cc, $bcc, $replyTo);

        $email->subject($subject);
        $email->subject($subject);
        $email->text($textBody);
        $email->html($htmlBody);

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
    public function sendSimpleEmailMessage(array $from, array $to, string $subject, string $htmlBody, string $textBody, array $cc = [], array $bcc = [], array $replyTo = []): void
    {
        $email = $this->createSimpleEmailMessage($from, $to, $subject, $htmlBody, $textBody, $cc, $bcc, $replyTo);

        $this->send($email);
    }

    /**
     * @param array $from    From addresses. An array of (email-address => name)
     * @param array $to      To addresses. An array of (email-address => name)
     * @param array $cc      Cc addresses. An array of (email-address => name) [optional]
     * @param array $bcc     Bcc addresses. An array of (email-address => name) [optional]
     * @param array $replyTo Reply to addresses. An array of (email-address => name) [optional]
     */
    protected function setupMessageHeaders(Email $email, array $from, array $to, array $cc = [], array $bcc = [], array $replyTo = []): void
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

    /**
     * @throws \Exception
     */
    protected function getParser(string $template): ParserInterface
    {
        $path = $this->templateHelper->getActiveMailTemplate()->getAbsolutePath();
        $parser = $this->parserResolver->getParser($path, $template);

        $parser->setTemplateDefinition(
            $parser->getTemplateDefinition() ?: $this->templateHelper->getActiveMailTemplate()
        );

        return $parser;
    }
}
