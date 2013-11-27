<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Event\Message;
use Thelia\Core\Event\Message\MessageCreateEvent;

class MessageUpdateEvent extends MessageCreateEvent
{
    protected $message_id;

    protected $html_layout_file_name;
    protected $html_template_file_name;

    protected $text_layout_file_name;
    protected $text_template_file_name;

    protected $text_message;
    protected $html_message;
    protected $subject;

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