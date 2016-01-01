<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Event\Country;

/**
 * Class CountryUpdateEvent
 * @package Thelia\Core\Event\Country
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CountryUpdateEvent extends CountryCreateEvent
{
    protected $country_id;

    /** @var bool */
    protected $needZipCode;
    /** @var string */
    protected $zipCodeFormat;
    /** @var int area zone */

    protected $chapo;
    protected $description;
    protected $postscriptum;

    public function __construct($country_id)
    {
        $this->country_id = $country_id;
    }

    /**
     * @param mixed $chapo
     */
    public function setChapo($chapo)
    {
        $this->chapo = $chapo;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getChapo()
    {
        return $this->chapo;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $postscriptum
     */
    public function setPostscriptum($postscriptum)
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPostscriptum()
    {
        return $this->postscriptum;
    }

    /**
     * @param mixed $country_id
     */
    public function setCountryId($country_id)
    {
        $this->country_id = $country_id;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCountryId()
    {
        return $this->country_id;
    }

    /**
     * @return boolean
     */
    public function isNeedZipCode()
    {
        return $this->needZipCode;
    }

    /**
     * @param boolean $needZipCode
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
     */
    public function setZipCodeFormat($zipCodeFormat)
    {
        $this->zipCodeFormat = $zipCodeFormat;
        return $this;
    }
}
