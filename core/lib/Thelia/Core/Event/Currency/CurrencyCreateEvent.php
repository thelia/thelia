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

namespace Thelia\Core\Event\Currency;

class CurrencyCreateEvent extends CurrencyEvent
{
    protected $currency_name;
    protected $locale;
    protected $symbol;
    protected $format;
    protected $code;
    protected $rate;

    // Use currency_name to prevent conflict with Event::name property.
    public function getCurrencyName()
    {
        return $this->currency_name;
    }

    public function setCurrencyName($currency_name): static
    {
        $this->currency_name = $currency_name;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setLocale($locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getSymbol()
    {
        return $this->symbol;
    }

    public function setSymbol($symbol): static
    {
        $this->symbol = $symbol;

        return $this;
    }

    public function getFormat()
    {
        return $this->format;
    }

    public function setFormat($format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setCode($code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getRate()
    {
        return $this->rate;
    }

    public function setRate($rate): static
    {
        $this->rate = $rate;

        return $this;
    }
}
