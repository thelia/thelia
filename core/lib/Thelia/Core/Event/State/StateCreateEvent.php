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

namespace Thelia\Core\Event\State;

/**
 * Class StateCreateEvent
 * @package Thelia\Core\Event\State
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
     * @return int
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @param int $country
     */
    public function setCountry($country)
    {
        $this->country = $country;
        return $this;
    }
}
