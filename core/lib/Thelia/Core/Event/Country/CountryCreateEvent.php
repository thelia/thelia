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
 * Class CountryCreateEvent
 * @package Thelia\Core\Event\Country
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
    protected $visible;
    /** @var bool has states */
    protected $hasStates;

    protected $area;

    /**
     * @param mixed $isoAlpha2
     */
    public function setIsoAlpha2($isoAlpha2)
    {
        $this->isoAlpha2 = $isoAlpha2;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getIsoAlpha2()
    {
        return $this->isoAlpha2;
    }

    /**
     * @param mixed $isoAlpha3
     */
    public function setIsoAlpha3($isoAlpha3)
    {
        $this->isoAlpha3 = $isoAlpha3;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getIsoAlpha3()
    {
        return $this->isoAlpha3;
    }

    /**
     * @param mixed $isocode
     */
    public function setIsocode($isocode)
    {
        $this->isocode = $isocode;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getIsocode()
    {
        return $this->isocode;
    }

    /**
     * @param mixed $locale
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param mixed $title
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param int $area
     */
    public function setArea($area)
    {
        $this->area = $area;

        return $this;
    }

    /**
     * @return int
     */
    public function getArea()
    {
        return $this->area;
    }

    /**
     * @return boolean
     */
    public function isVisible()
    {
        return $this->visible;
    }

    /**
     * @param boolean $visible
     */
    public function setVisible($visible)
    {
        $this->visible = $visible;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isHasStates()
    {
        return $this->hasStates;
    }

    /**
     * @param boolean $hasStates
     */
    public function setHasStates($hasStates)
    {
        $this->hasStates = $hasStates;
        return $this;
    }
}
