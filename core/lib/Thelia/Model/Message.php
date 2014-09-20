<?php

namespace Thelia\Model;

use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Model\Base\Message as BaseMessage;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Message\MessageEvent;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelper;

class Message extends BaseMessage
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_CREATEMESSAGE, new MessageEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_CREATEMESSAGE, new MessageEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATEMESSAGE, new MessageEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATEMESSAGE, new MessageEvent($this));
    }

    /**
     * {@inheritDoc}
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETEMESSAGE, new MessageEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_DELETEMESSAGE, new MessageEvent($this));
    }

    /**
     * Calculate the message body, given the HTML entered in the back-office, the message layout, and the message template

     * @param  ParserInterface $parser
     * @param $message
     * @param $layout
     * @param $template
     * @return bool
     */
    protected function getMessageBody($parser, $message, $layout, $template, $compressOutput = true)
    {
        $body = false;

        // Try to get the body from template file, if a file is defined
        if (! empty($template)) {
            try {
                $body = $parser->render($template, [], $compressOutput);
            } catch (ResourceNotFoundException $ex) {
                // Ignore this.
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
     * Get the TEXT message body
     */
    public function getTextMessageBody(ParserInterface $parser)
    {
        return $this->getMessageBody(
                $parser,
                $this->getTextMessage(),
                $this->getTextLayoutFileName(),
                $this->getTextTemplateFileName(),
                true // Do not compress the output, and keep empty lines.
        );
    }

    /**
     * Add a subject and a body (TEXT, HTML or both, depending on the message
     * configuration.
     */
    public function buildMessage($parser, \Swift_Message $messageInstance)
    {
        $parser->setTemplateDefinition(TemplateHelper::getInstance()->getActiveMailTemplate());
        $subject     = $parser->fetch(sprintf("string:%s", $this->getSubject()));
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

        return $messageInstance;
    }
}
