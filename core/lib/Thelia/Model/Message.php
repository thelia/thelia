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

namespace Thelia\Model;

use Symfony\Component\Mime\Email;
use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\ParserInterface;
use Thelia\Log\Tlog;
use Thelia\Model\Base\Message as BaseMessage;

class Message extends BaseMessage
{
    /**
     * Calculate the message body, given the HTML entered in the back-office, the message layout, and the message template.
     *
     * @throws \Exception
     */
    protected function getMessageBody(
        ParserInterface $parser,
        ?string $message,
        $layout,
        $template,
        bool $compressOutput = true,
    ): bool|string {
        $body = false;

        // Try to get the body from template file, if a file is defined
        if (!empty($template)) {
            try {
                $body = $parser->render($template, [], $compressOutput);
            } catch (ResourceNotFoundException) {
                Tlog::getInstance()->addError('Failed to get mail message template body '.$template);
            }
        }

        // We did not get it ? Use the message entered in the back-office
        if (false === $body) {
            $body = $parser->renderString($message, [], $compressOutput);
        }

        // Do we have a layout ?
        if (!empty($layout)) {
            // Populate the message body variable
            $parser->assign('message_body', $body);

            // Render the layout file
            $body = $parser->render($layout, [], $compressOutput);
        }

        return $body;
    }

    /**
     * Get the HTML message body.
     */
    public function getHtmlMessageBody(ParserInterface $parser): bool|string
    {
        return $this->getMessageBody(
            $parser,
            $this->getHtmlMessage(),
            $this->getHtmlLayoutFileName(),
            $this->getHtmlTemplateFileName(),
        );
    }

    /**
     * @return string|string[]|null
     */
    public function getTextMessageBody(ParserInterface $parser): string|array|null
    {
        $message = $this->getMessageBody(
            $parser,
            $this->getTextMessage(),
            $this->getTextLayoutFileName(),
            $this->getTextTemplateFileName(),
            true, // Do not compress the output, and keep empty lines.
        );

        // Replaced all <br> by newlines.
        return preg_replace('/<br>/i', "\n", $message);
    }

    /**
     * The subject of the message in the locale it is set to, or the closest one a shop has.
     *
     * A message whose subject was never translated into the locale the mail is being sent in
     * would otherwise leave the shop, silently, with no subject line at all: the seed of a
     * Thelia 3.0 shop shipped that hole in five of its eight locales, and no seed can cover
     * a language a merchant adds afterwards. The wording of another language is a degraded
     * subject, an empty one is no subject.
     *
     * The default language of the shop comes first, being the one a merchant is the most
     * likely to have written; any other translation, in locale order so the same shop always
     * picks the same one, comes after. The body follows the same idea one level up, in
     * getMessageBody(): a template file first, the stored body when there is none.
     */
    public function getSubjectWithFallback(): string
    {
        $subject = trim((string) $this->getSubject());

        if ('' !== $subject) {
            return $subject;
        }

        $translations = MessageI18nQuery::create()
            ->filterById($this->getId())
            ->orderByLocale()
            ->find();

        $subjects = [];

        foreach ($translations as $translation) {
            $translatedSubject = trim((string) $translation->getSubject());

            if ('' !== $translatedSubject) {
                $subjects[$translation->getLocale()] = $translatedSubject;
            }
        }

        if ([] === $subjects) {
            // Defined, not accidental: the mail is still worth sending without a subject —
            // it carries the confirmation link a customer is waiting for — but nothing else
            // in the shop would ever report that the wording is missing.
            Tlog::getInstance()->addError(
                \sprintf('Message %s has no subject in any locale: the mail is sent without a subject line.', $this->getName()),
            );

            return '';
        }

        $defaultLocale = LangQuery::create()->findOneByByDefault(1)?->getLocale();
        $fallbackLocale = null !== $defaultLocale && isset($subjects[$defaultLocale])
            ? $defaultLocale
            : array_key_first($subjects);

        Tlog::getInstance()->addWarning(
            \sprintf('Message %s has no subject in %s: the %s one is used instead.', $this->getName(), $this->getLocale(), $fallbackLocale),
        );

        return $subjects[$fallbackLocale];
    }

    /**
     * Add a subject and a body (TEXT, HTML or both, depending on the message
     * configuration.
     *
     * @param bool $useFallbackTemplate when we send mail from a module and don't use the `default` email
     *                                  template, if the file (html/txt) is not found in the template then
     *                                  the template file located in the module under
     *                                  `templates/email/default/' directory is used if
     *                                  `$useFallbackTemplate` is set to `true`
     *
     * @throws \Exception
     */
    public function buildMessage(ParserInterface $parser, Email $messageInstance, bool $useFallbackTemplate = true): Email
    {
        // Set mail template, and save the current template
        $parser->pushTemplateDefinition(
            $parser->getTemplateHelper()->getActiveMailTemplate(),
            $useFallbackTemplate,
        );

        try {
            $subject = $parser->renderString($this->getSubjectWithFallback());
            $htmlMessage = $this->getHtmlMessageBody($parser);
            $textMessage = $this->getTextMessageBody($parser);

            if (empty($htmlMessage) && empty($textMessage)) {
                throw new \RuntimeException('Message body is empty for message ID: '.$this->getId());
            }

            $messageInstance->subject($subject);
            $messageInstance->text($textMessage);
            $messageInstance->html($htmlMessage);
        } finally {
            // Restore the previous template on every path: MailerFactory::sendEmailMessage()
            // swallows rendering failures so the request survives them, which would otherwise
            // leave the parser stuck on the mail template for the rest of the request (e.g. a
            // payment module rendering its own template right after order notification emails).
            $parser->popTemplateDefinition();
        }

        return $messageInstance;
    }
}
