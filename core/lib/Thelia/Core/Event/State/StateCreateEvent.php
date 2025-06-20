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
namespace Thelia\Core\Event\State;

/**
 * Class StateCreateEvent.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StateCreateEvent extends StateEvent
{
    protected $locale;

    protected $title;

    protected $isocode;

    /** @var bool is visible */
    protected $visible;

    /** @var int */
    protected $country;

    public function setIsocode($isocode): static
    {
        $this->isocode = $isocode;

        return $this;
    }

    public function getIsocode()
    {
        return $this->isocode;
    }

    public function setLocale($locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setTitle($title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return bool
     */
    public function isVisible()
    {
        return $this->visible;
    }

    /**
     * @param bool $visible
     */
    public function setVisible($visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * @return int
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @param int $country
     */
    public function setCountry($country): static
    {
        $this->country = $country;

        return $this;
    }
}
