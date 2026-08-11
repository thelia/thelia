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
    protected ?string $currency_name = null;
    protected ?string $locale = null;
    protected ?string $symbol = null;
    protected ?string $format = null;
    protected ?string $code = null;
    protected ?string $isocode_numeric = null;
    protected ?float $rate = null;

    // Use currency_name to prevent conflict with Event::name property.
    public function getCurrencyName(): ?string
    {
        return $this->currency_name;
    }

    public function setCurrencyName(?string $currency_name): static
    {
        $this->currency_name = $currency_name;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getSymbol(): ?string
    {
        return $this->symbol;
    }

    public function setSymbol(?string $symbol): static
    {
        $this->symbol = $symbol;

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getIsocodeNumeric(): ?string
    {
        return $this->isocode_numeric;
    }

    public function setIsocodeNumeric(?string $isocode_numeric): static
    {
        $this->isocode_numeric = $isocode_numeric;

        return $this;
    }

    public function getRate(): ?float
    {
        return $this->rate;
    }

    public function setRate(?float $rate): static
    {
        $this->rate = $rate;

        return $this;
    }
}
