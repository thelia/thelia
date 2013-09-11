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

namespace Thelia\Core\Event;

class MessageUpdateEvent extends MessageCreateEvent
{
    protected $message_id;

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
}
