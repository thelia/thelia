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

namespace Thelia\Core\Event\Country;

/**
 * Class CountryCreateEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CountryCreateEvent extends CountryEvent
{
    protected $locale;
    protected $title;
    protected $isocode;
    protected $isoAlpha2;
    protected $isoAlpha3;

    /** @var bool is visible */
    protected bool $visible;

    /** @var bool has states */
    protected bool $hasStates;

    protected $area;

    public function setIsoAlpha2($isoAlpha2): static
    {
        $this->isoAlpha2 = $isoAlpha2;

        return $this;
    }

    public function getIsoAlpha2()
    {
        return $this->isoAlpha2;
    }

    public function setIsoAlpha3($isoAlpha3): static
    {
        $this->isoAlpha3 = $isoAlpha3;

        return $this;
    }

    public function getIsoAlpha3()
    {
        return $this->isoAlpha3;
    }

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

    public function setArea(int $area): static
    {
        $this->area = $area;

        return $this;
    }

    public function getArea(): int
    {
        return $this->area;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function isHasStates(): bool
    {
        return $this->hasStates;
    }

    public function setHasStates(bool $hasStates): static
    {
        $this->hasStates = $hasStates;

        return $this;
    }
}
