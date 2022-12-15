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

namespace Thelia\Core\Event\Country;

/**
 * Class CountryUpdateEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CountryUpdateEvent extends CountryCreateEvent
{
    protected $country_id;

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

    public function __construct(int $country_id)
    {
        $this->country_id = $country_id;
    }

    public function setChapo(?string $chapo)
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

    public function setDescription(?string $description)
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setPostscriptum(?string $postscriptum)
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    public function getPostscriptum()
    {
        return $this->postscriptum;
    }

    /**
     * @param int $country_id
     *
     * @return $this
     */
    public function setCountryId($country_id)
    {
        $this->country_id = $country_id;

        return $this;
    }

    /**
     * @return int
     */
    public function getCountryId()
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
    public function setNeedZipCode($needZipCode)
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
    public function setZipCodeFormat($zipCodeFormat)
    {
        $this->zipCodeFormat = $zipCodeFormat;

        return $this;
    }
}
