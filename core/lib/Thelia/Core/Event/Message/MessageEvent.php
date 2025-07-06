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

namespace Thelia\Core\Event\Message;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Message;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\MessageEvent
 */
class MessageEvent extends ActionEvent
{
    public function __construct(protected ?Message $message = null)
    {
    }

    public function hasMessage(): bool
    {
        return $this->message instanceof Message;
    }

    public function getMessage(): ?Message
    {
        return $this->message;
    }

    public function setMessage(?Message $message): static
    {
        $this->message = $message;

        return $this;
    }
}
