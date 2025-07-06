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

namespace Thelia\Core\Event\Lang;

/**
 * Class LangCreateEvent.
 *
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
     * @return $this
     */
    public function setCode($code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getDateTimeFormat()
    {
        return $this->date_time_format;
    }

    public function setDateTimeFormat($date_time_format): static
    {
        $this->date_time_format = $date_time_format;

        return $this;
    }

    /**
     * @return $this
     */
    public function setDateFormat($date_format): static
    {
        $this->date_format = $date_format;

        return $this;
    }

    public function getDateFormat()
    {
        return $this->date_format;
    }

    /**
     * @return $this
     */
    public function setLocale($locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @return $this
     */
    public function setTimeFormat($time_format): static
    {
        $this->time_format = $time_format;

        return $this;
    }

    public function getTimeFormat()
    {
        return $this->time_format;
    }

    /**
     * @return $this
     */
    public function setTitle($title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setDecimalSeparator($decimal_separator): static
    {
        $this->decimal_separator = $decimal_separator;

        return $this;
    }

    public function getDecimalSeparator()
    {
        return $this->decimal_separator;
    }

    public function setDecimals($decimals): static
    {
        $this->decimals = $decimals;

        return $this;
    }

    public function getDecimals()
    {
        return $this->decimals;
    }

    public function setThousandsSeparator($thousands_separator): static
    {
        $this->thousands_separator = $thousands_separator;

        return $this;
    }

    public function getThousandsSeparator()
    {
        return $this->thousands_separator;
    }

    /**
     * @param bool $active
     *
     * @return $this
     */
    public function setActive($active): static
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
     *
     * @return $this
     */
    public function setVisible($visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * @return bool
     */
    public function getVisible()
    {
        return $this->visible;
    }
}
