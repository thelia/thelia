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

namespace Thelia\Core\Event\Message;

class MessageUpdateEvent extends MessageCreateEvent
{
    /** @var int */
    protected $message_id;

    protected $html_layout_file_name;
    protected $html_template_file_name;

    protected $text_layout_file_name;
    protected $text_template_file_name;

    protected $text_message;
    protected $html_message;
    protected $subject;

    /**
     * @param int $message_id
     */
    public function __construct($message_id)
    {
        $this->setMessageId($message_id);
    }

    public function getMessageId()
    {
        return $this->message_id;
    }

    public function setMessageId($message_id)
    {
        $this->message_id = $message_id;

        return $this;
    }

    public function getTextMessage()
    {
        return $this->text_message;
    }

    public function setTextMessage($text_message)
    {
        $this->text_message = $text_message;

        return $this;
    }

    public function getHtmlMessage()
    {
        return $this->html_message;
    }

    public function setHtmlMessage($html_message)
    {
        $this->html_message = $html_message;

        return $this;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function setSubject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    public function getHtmlLayoutFileName()
    {
        return $this->html_layout_file_name;
    }

    public function setHtmlLayoutFileName($html_layout_file_name)
    {
        $this->html_layout_file_name = $html_layout_file_name;

        return $this;
    }

    public function getHtmlTemplateFileName()
    {
        return $this->html_template_file_name;
    }

    public function setHtmlTemplateFileName($html_template_file_name)
    {
        $this->html_template_file_name = $html_template_file_name;

        return $this;
    }

    public function getTextLayoutFileName()
    {
        return $this->text_layout_file_name;
    }

    public function setTextLayoutFileName($text_layout_file_name)
    {
        $this->text_layout_file_name = $text_layout_file_name;

        return $this;
    }

    public function getTextTemplateFileName()
    {
        return $this->text_template_file_name;
    }

    public function setTextTemplateFileName($text_template_file_name)
    {
        $this->text_template_file_name = $text_template_file_name;

        return $this;
    }
}
