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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Log\Tlog;
use Thelia\Mailer\EventListener\OrderEmailHistoryListener;
use Thelia\Mailer\Exception\EmailNotSentException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\MessageQuery;
use Thelia\Model\OrderQuery;

/**
 * Class MailerFactory.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class MailerFactory
{
    /**
     * The parameter names an email is written against to name the order it is about.
     *
     * Nothing in the signature of a send says which order a message concerns — the
     * order confirmation, the shop notification, the cheque payment confirmation, the
     * virtual product download and the return status change all pass it as an ordinary
     * template parameter instead. Those two names are the convention every caller in
     * the core and in the shipped modules already follows, so they are what is read
     * here: `order_id` when it is there, and `order_ref` to look the order up when it
     * is the only one passed. A message carrying neither is not about an order and
     * records nothing.
     */
    private const ORDER_ID_PARAMETER = 'order_id';
    private const ORDER_REF_PARAMETER = 'order_ref';

    public function __construct(
        private readonly TemplateHelperInterface $templateHelper,
        private readonly ParserResolver $parserResolver,
        private readonly MailerInterface $mailer,
        #[Autowire(service: 'mailer.transports')]
        private readonly TransportInterface $transport,
    ) {
    }

    public function send(Email $message): void
    {
        $this->mailer->send($message);
    }

    /**
     * Hands the message to the mail server now, whether the shop has a queue or not:
     * for a test of the mail configuration, whose only point is the server's answer.
     * Any refusal is thrown to the caller.
     */
    public function sendNow(Email $message): void
    {
        $this->transport->send($message);
    }

    /**
     * Builds a message of the shop and hands it to the mail server now, queue or not:
     * for a test of the message, whose only point is the server's answer. Nothing is
     * written in the history of an order the test variables may name.
     *
     * @param array<string, mixed> $messageParameters
     *
     * @throws EmailNotSentException when the shop has no address to send from
     */
    public function sendTestMessage(string $messageCode, string $recipient, array $messageParameters = [], ?string $locale = null): void
    {
        $this->sendNow($this->createEmailMessage($messageCode, $this->testSender($messageCode), [$recipient => $recipient], $messageParameters, $locale));
    }

    /**
     * Sends a mail of the given subject and body to test the mail settings, handed to the
     * mail server now, queue or not.
     *
     * @throws EmailNotSentException when the shop has no address to send from
     */
    public function sendTestMail(string $recipient, string $subject, string $htmlBody): void
    {
        $this->sendNow($this->createSimpleEmailMessage($this->testSender('mail_settings_test'), [$recipient => $recipient], $subject, $htmlBody, strip_tags($htmlBody)));
    }

    /**
     * @return array<string, string> the address and the name of the shop
     *
     * @throws EmailNotSentException when the shop has no address to send from
     */
    private function testSender(string $messageCode): array
    {
        $storeEmail = (string) ConfigQuery::getStoreEmail();

        if ('' === $storeEmail) {
            throw EmailNotSentException::storeEmailMissing($messageCode);
        }

        return [$storeEmail => (string) ConfigQuery::getStoreName()];
    }

    /**
     * Send a message to the customer.
     *
     * A message that cannot be sent is logged and swallowed: a mail is never worth
     * breaking the process that asked for it. A caller that has to know uses
     * {@see self::sendEmailToCustomerOrFail()}.
     *
     * @param array $messageParameters an array of (name => value) parameters that will be available in the message
     */
    public function sendEmailToCustomer(string $messageCode, Customer $customer, array $messageParameters = []): void
    {
        try {
            $this->sendEmailToCustomerOrFail($messageCode, $customer, $messageParameters);
        } catch (EmailNotSentException) {
            // Already logged where it was raised.
        }
    }

    /**
     * Send a message to the customer, and tell the caller when it did not leave.
     *
     * @param array $messageParameters an array of (name => value) parameters that will be available in the message
     *
     * @throws EmailNotSentException
     */
    public function sendEmailToCustomerOrFail(string $messageCode, Customer $customer, array $messageParameters = []): void
    {
        // Always add the customer ID to the parameters
        $messageParameters['customer_id'] = $customer->getId();

        $this->sendEmailMessageOrFail(
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
        try {
            $this->sendEmailToShopManagersOrFail($messageCode, $messageParameters, $replyTo);
        } catch (EmailNotSentException) {
            // Already logged where it was raised.
        }
    }

    /**
     * Send a message to the shop managers, and tell the caller when it did not leave.
     *
     * @param array $messageParameters an array of (name => value) parameters that will be available in the message
     * @param array $replyTo           Reply to addresses. An array of (email-address => name) [optional]
     *
     * @throws EmailNotSentException
     */
    public function sendEmailToShopManagersOrFail(string $messageCode, array $messageParameters = [], array $replyTo = []): void
    {
        $storeName = ConfigQuery::getStoreName();

        // Build the list of email recipients
        $recipients = ConfigQuery::getNotificationEmailsList();

        $to = [];

        foreach ($recipients as $recipient) {
            $to[$recipient] = $storeName;
        }

        if ([] === $to) {
            $exception = EmailNotSentException::noShopNotificationRecipient($messageCode);
            Tlog::getInstance()->addError($exception->getMessage());

            throw $exception;
        }

        $this->sendEmailMessageOrFail(
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
        try {
            $this->sendEmailMessageOrFail($messageCode, $from, $to, $messageParameters, $locale, $cc, $bcc, $replyTo);
        } catch (EmailNotSentException) {
            // Already logged where it was raised.
        }
    }

    /**
     * Send a message built from a message code, and tell the caller when it did not leave.
     *
     * The single place a message is actually handed to the transport. What went wrong
     * is logged here, with everything the server may need; what the exception carries
     * is what a caller may show, and names neither a recipient nor a transport.
     *
     * With a queue, "sent" means queued: only a mail that could not be built or queued
     * throws here. One the mail server refuses later is set aside with the failed jobs.
     *
     * @param array       $from              From addresses. An array of (email-address => name)
     * @param array       $to                To addresses. An array of (email-address => name)
     * @param array       $messageParameters an array of (name => value) parameters that will be available in the message
     * @param string|null $locale            if null, the default store locale is used
     * @param array       $cc                Cc addresses. An array of (email-address => name) [optional]
     * @param array       $bcc               Bcc addresses. An array of (email-address => name) [optional]
     * @param array       $replyTo           Reply to addresses. An array of (email-address => name) [optional]
     *
     * @throws EmailNotSentException
     */
    public function sendEmailMessageOrFail(
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
            $exception = EmailNotSentException::storeEmailMissing($messageCode);
            Tlog::getInstance()->addError($exception->getMessage());

            throw $exception;
        }
        if ([] === $to) {
            $exception = EmailNotSentException::emptyRecipientList($messageCode);
            Tlog::getInstance()->addWarning($exception->getMessage());

            throw $exception;
        }

        try {
            $instance = $this->createEmailMessage($messageCode, $from, $to, $messageParameters, $locale, $cc, $bcc, $replyTo);
            $this->tagWithTheOrderItIsAbout($instance, $messageCode, $messageParameters);

            $this->send($instance);
        } catch (\Exception $ex) {
            // The raw reason names the recipient: the server log is the only place
            // for it. The credentials of the transport are not even wanted there.
            Tlog::getInstance()->addError(
                \sprintf('Error while sending email message %s: ', $messageCode).TransportCredentials::hide($ex->getMessage()),
            );

            throw EmailNotSentException::sendingFailed($messageCode, $ex);
        }
    }

    /**
     * Names, on the mail itself, the order it is about, when it is about one.
     *
     * The line in the order history is written once the mail server has accepted the
     * mail ({@see OrderEmailHistoryListener}), which is in this request when the shop
     * has no queue and in a worker, minutes later, when it has one: an order history
     * saying a customer was written to when nothing left the shop is worse than one
     * that says nothing. Only the order id and the message code travel, never the
     * body or the address, in headers of the mail that the history listener takes
     * off before the mail leaves the shop: the customer never receives them.
     *
     * @param array<string, mixed> $messageParameters
     */
    private function tagWithTheOrderItIsAbout(Email $email, string $messageCode, array $messageParameters): void
    {
        $orderId = $this->orderIdFromMessageParameters($messageParameters);

        if (null === $orderId) {
            return;
        }

        $email->getHeaders()
            ->addTextHeader(OrderEmailHistoryListener::ORDER_ID_HEADER, (string) $orderId)
            ->addTextHeader(OrderEmailHistoryListener::MESSAGE_CODE_HEADER, $messageCode);
    }

    /**
     * @param array<string, mixed> $messageParameters
     */
    private function orderIdFromMessageParameters(array $messageParameters): ?int
    {
        $orderId = $messageParameters[self::ORDER_ID_PARAMETER] ?? null;

        if (\is_int($orderId) && $orderId > 0) {
            return $orderId;
        }

        if (\is_string($orderId) && ctype_digit($orderId) && (int) $orderId > 0) {
            return (int) $orderId;
        }

        $orderRef = $messageParameters[self::ORDER_REF_PARAMETER] ?? null;

        if (!\is_string($orderRef) || '' === $orderRef) {
            return null;
        }

        return OrderQuery::create()->findOneByRef($orderRef)?->getId();
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
        // Select the parser from the actual template file base name (e.g. "password"), not the
        // message code (e.g. "lost_password"): the two frequently differ, and the parser is
        // chosen by testing whether a matching template file exists. Using the code would make
        // that existence test miss the real file and fall back to the wrong engine.
        $templateFileName = (string) ($message->getHtmlTemplateFileName() ?: $message->getTextTemplateFileName());
        $parser = $this->getParser(
            '' !== $templateFileName ? pathinfo($templateFileName, \PATHINFO_FILENAME) : null
        );
        // Assign parameters
        foreach ($messageParameters as $name => $value) {
            $parser->assign($name, $value);
        }

        // As the parser uses the lang stored in the session, temporarly set the required language into the session.
        // This is required in the back office when sending emails to customers, that may use a different locale than
        // the current one.
        // There is no session on the command line, where emails are sent from commands and
        // scheduled tasks, so nothing to swap there: the requested locale is then only carried
        // by the message itself.
        $request = $parser->getRequest();
        $session = $request?->hasSession() ? $request->getSession() : null;
        $session = $session instanceof Session ? $session : null;

        $currentLang = null !== $session
            ? (TheliaRequest::$isAdminEnv ? $session->getAdminLang() : $session->getLang())
            : null;

        if (null !== $requiredLang = LangQuery::create()->findOneByLocale($locale)) {
            $this->setLanguageSession($session, $requiredLang);
        }

        try {
            $email = (new Email());

            $this->setupMessageHeaders($email, $from, $to, $cc, $bcc, $replyTo);

            $message->buildMessage($parser, $email);

            return $email;
        } finally {
            // Restore on every path: sendEmailMessage() deliberately swallows a rendering
            // failure so the request survives it, which would otherwise leave the visitor's
            // session in the language of an email they never received.
            $this->setLanguageSession($session, $currentLang);
        }
    }

    /**
     * Session::getLang(), which the parsers and the translator read to localize a render,
     * returns the admin language as soon as the request runs in an admin environment. The
     * language of an email therefore has to be written to that slot there: swapping the front
     * office one would silently do nothing, and a customer email triggered by a back office
     * action would render in the language of the administrator's interface.
     */
    protected function setLanguageSession(?Session $session, ?Lang $lang): void
    {
        if (null === $session || null === $lang) {
            return;
        }

        if (TheliaRequest::$isAdminEnv) {
            $session->setAdminLang($lang);
        } else {
            $session->setLang($lang);
        }
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
    protected function getParser(?string $template): ParserInterface
    {
        // A message with no template file at all renders from its database-stored body,
        // so there is no file for a parser to claim: asking the resolver for one would
        // report a missing resource, and sendEmailMessage() would swallow it — the mail
        // silently lost. The default parser renders the stored body instead.
        $path = $this->templateHelper->getActiveMailTemplate()->getAbsolutePath();
        $parser = null === $template
            ? $this->parserResolver->getDefaultParser()
            : $this->parserResolver->getParser($path, $template);

        $parser->setTemplateDefinition(
            $parser->getTemplateDefinition() ?: $this->templateHelper->getActiveMailTemplate()
        );

        return $parser;
    }
}
