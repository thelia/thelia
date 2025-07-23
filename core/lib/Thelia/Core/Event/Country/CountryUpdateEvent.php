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
 * Class CountryUpdateEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CountryUpdateEvent extends CountryCreateEvent
{
    protected bool $needZipCode;
    protected string $zipCodeFormat;
    protected string $chapo;
    protected string $description;
    protected string $postscriptum;

    public function __construct(protected int $country_id)
    {
    }

    public function setChapo(?string $chapo): static
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getChapo(): string
    {
        return $this->chapo;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setPostscriptum(?string $postscriptum): static
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    public function getPostscriptum(): string
    {
        return $this->postscriptum;
    }

    /**
     * @return $this
     */
    public function setCountryId(int $country_id): static
    {
        $this->country_id = $country_id;

        return $this;
    }

    public function getCountryId(): int
    {
        return $this->country_id;
    }

    public function isNeedZipCode(): string
    {
        return $this->needZipCode;
    }

    /**
     * @return $this
     */
    public function setNeedZipCode(bool $needZipCode): static
    {
        $this->needZipCode = $needZipCode;

        return $this;
    }

    public function getZipCodeFormat(): string
    {
        return $this->zipCodeFormat;
    }

    /**
     * @return $this
     */
    public function setZipCodeFormat(string $zipCodeFormat): static
    {
        $this->zipCodeFormat = $zipCodeFormat;

        return $this;
    }
}
