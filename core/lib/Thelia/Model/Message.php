<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Message\MessageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\ParserInterface;
use Thelia\Log\Tlog;
use Thelia\Model\Base\Message as BaseMessage;

class Message extends BaseMessage
{
    /**
     * Calculate the message body, given the HTML entered in the back-office, the message layout, and the message template
     * @param ParserInterface $parser
     * @param string $message
     * @param $layout
     * @param $template
     * @param bool $compressOutput
     * @return bool|string
     * @throws \SmartyException
     */
    protected function getMessageBody($parser, $message, $layout, $template, $compressOutput = true)
    {
        $body = false;

        // Try to get the body from template file, if a file is defined
        if (! empty($template)) {
            try {
                $body = $parser->render($template, [], $compressOutput);
            } catch (ResourceNotFoundException $ex) {
                Tlog::getInstance()->addError("Failed to get mail message template body $template");
            }
        }

        // We did not get it ? Use the message entered in the back-office
        if ($body === false) {
            $body = $parser->renderString($message, [], $compressOutput);
        }

        // Do we have a layout ?
        if (! empty($layout)) {
            // Populate the message body variable
            $parser->assign('message_body', $body);

            // Render the layout file
            $body = $parser->render($layout, [], $compressOutput);
        }

        return $body;
    }

    /**
     * Get the HTML message body
     * @return bool|string
     * @throws \SmartyException
     */
    public function getHtmlMessageBody(ParserInterface $parser)
    {
        return $this->getMessageBody(
            $parser,
            $this->getHtmlMessage(),
            $this->getHtmlLayoutFileName(),
            $this->getHtmlTemplateFileName()
        );
    }

    /**
     * @return string|string[]|null
     * @throws \SmartyException
     */
    public function getTextMessageBody(ParserInterface $parser)
    {
        $message = $this->getMessageBody(
            $parser,
            $this->getTextMessage(),
            $this->getTextLayoutFileName(),
            $this->getTextTemplateFileName(),
            true // Do not compress the output, and keep empty lines.
        );

        // Replaced all <br> by newlines.
        return preg_replace("/<br>/i", "\n", $message);
    }

    /**
     * Add a subject and a body (TEXT, HTML or both, depending on the message
     * configuration.
     *
     * @param  bool            $useFallbackTemplate When we send mail from a module and don't use the `default` email
     *                                              template, if the file (html/txt) is not found in the template then
     *                                              the template file located in the module under
     *                                              `templates/email/default/' directory is used if
     *                                              `$useFallbackTemplate` is set to `true`.
     * @return \Swift_Message
     * @throws \SmartyException
     */
    public function buildMessage(ParserInterface $parser, \Swift_Message $messageInstance, $useFallbackTemplate = true)
    {
        // Set mail template, and save the current template
        $parser->pushTemplateDefinition(
            $parser->getTemplateHelper()->getActiveMailTemplate(),
            $useFallbackTemplate
        );

        $subject     = $parser->renderString($this->getSubject());
        $htmlMessage = $this->getHtmlMessageBody($parser);
        $textMessage = $this->getTextMessageBody($parser);

        $messageInstance->setSubject($subject);

        // If we do not have an HTML message
        if (empty($htmlMessage)) {
            // Message body is the text message
            $messageInstance->setBody($textMessage, 'text/plain');
        } else {
            // The main body is the HTML messahe
            $messageInstance->setBody($htmlMessage, 'text/html');

            // Use the text as a message part, if we have one.
            if (! empty($textMessage)) {
                $messageInstance->addPart($textMessage, 'text/plain');
            }
        }

        // Restore previous template
        $parser->popTemplateDefinition();

        return $messageInstance;
    }
}
