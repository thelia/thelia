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

namespace Thelia\Core\Event\Message;

class MessageDeleteEvent extends MessageEvent
{
    /** @var int */
    protected $message_id;

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
}
