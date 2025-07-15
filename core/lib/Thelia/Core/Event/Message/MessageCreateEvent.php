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

class MessageCreateEvent extends MessageEvent
{
    protected $message_name;

    protected $locale;

    protected $title;

    protected $secured;

    // Use message_name to prevent conflict with Event::name property.
    public function getMessageName()
    {
        return $this->message_name;
    }

    public function setMessageName($message_name): static
    {
        $this->message_name = $message_name;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setLocale($locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSecured()
    {
        return $this->secured;
    }

    public function setSecured($secured): static
    {
        $this->secured = $secured;

        return $this;
    }
}
