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
    /** @var bool */
    protected $needZipCode;

    /** @var string */
    protected $zipCodeFormat;

    /** @var string */
    protected $chapo;

    /** @var string */
    protected $description;

    /** @var string */
    protected $postscriptum;

    public function __construct(protected int $country_id)
    {
    }

    public function setChapo(?string $chapo): static
    {
        $this->chapo = $chapo;

        return $this;
    }

    /**
     * @return string
     */
    public function getChapo()
    {
        return $this->chapo;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setPostscriptum(?string $postscriptum): static
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    public function getPostscriptum()
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

    /**
     * @return string
     */
    public function isNeedZipCode()
    {
        return $this->needZipCode;
    }

    /**
     * @param bool $needZipCode
     *
     * @return $this
     */
    public function setNeedZipCode($needZipCode): static
    {
        $this->needZipCode = $needZipCode;

        return $this;
    }

    /**
     * @return string
     */
    public function getZipCodeFormat()
    {
        return $this->zipCodeFormat;
    }

    /**
     * @param string $zipCodeFormat
     *
     * @return $this
     */
    public function setZipCodeFormat($zipCodeFormat): static
    {
        $this->zipCodeFormat = $zipCodeFormat;

        return $this;
    }
}
