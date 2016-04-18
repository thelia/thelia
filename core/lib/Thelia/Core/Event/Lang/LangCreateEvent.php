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

namespace Thelia\Core\Event\Lang;

/**
 * Class LangCreateEvent
 * @package Thelia\Core\Event\Lang
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class LangCreateEvent extends LangEvent
{
    protected $title;
    protected $code;
    protected $locale;
    protected $date_time_format;
    protected $date_format;
    protected $time_format;
    protected $decimal_separator;
    protected $thousands_separator;
    protected $decimals;
    /** @var bool */
    protected $active;
    /** @var bool */
    protected $visible;

    /**
     * @param mixed $code
     *
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return mixed
     */
    public function getDateTimeFormat()
    {
        return $this->date_time_format;
    }

    /**
     * @param mixed $date_time_format
     */
    public function setDateTimeFormat($date_time_format)
    {
        $this->date_time_format = $date_time_format;
        return $this;
    }


    /**
     * @param mixed $date_format
     *
     * @return $this
     */
    public function setDateFormat($date_format)
    {
        $this->date_format = $date_format;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDateFormat()
    {
        return $this->date_format;
    }

    /**
     * @param mixed $locale
     *
     * @return $this
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
     * @param mixed $time_format
     *
     * @return $this
     */
    public function setTimeFormat($time_format)
    {
        $this->time_format = $time_format;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTimeFormat()
    {
        return $this->time_format;
    }

    /**
     * @param mixed $title
     *
     * @return $this
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
     * @param mixed $decimal_separator
     */
    public function setDecimalSeparator($decimal_separator)
    {
        $this->decimal_separator = $decimal_separator;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDecimalSeparator()
    {
        return $this->decimal_separator;
    }

    /**
     * @param mixed $decimals
     */
    public function setDecimals($decimals)
    {
        $this->decimals = $decimals;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDecimals()
    {
        return $this->decimals;
    }

    /**
     * @param mixed $thousands_separator
     */
    public function setThousandsSeparator($thousands_separator)
    {
        $this->thousands_separator = $thousands_separator;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getThousandsSeparator()
    {
        return $this->thousands_separator;
    }

    /**
     * @param bool $active
     * @return $this
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return bool
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * @param bool $visible
     * @return $this
     */
    public function setVisible($visible)
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * @return bool
     */
    public function getVisible()
    {
        return$this->visible;
    }
}
