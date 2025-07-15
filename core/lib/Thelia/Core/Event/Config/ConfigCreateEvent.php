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

namespace Thelia\Core\Event\Config;

class ConfigCreateEvent extends ConfigEvent
{
    protected $event_name;

    protected $value;

    protected $locale;

    protected $title;

    protected $hidden;

    protected $secured;

    // Use event_name to prevent conflict with Event::name property.
    public function getEventName()
    {
        return $this->event_name;
    }

    public function setEventName($event_name): static
    {
        $this->event_name = $event_name;

        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value): static
    {
        $this->value = $value;

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

    public function getHidden()
    {
        return $this->hidden;
    }

    public function setHidden($hidden): static
    {
        $this->hidden = $hidden;

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
