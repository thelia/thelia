<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class CurrencyI18n extends I18n
{
    #[Groups([Currency::GROUP_READ])]
    protected string $locale;

    #[Groups([Currency::GROUP_READ])]
    protected ?string $name;

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): CurrencyI18n
    {
        $this->locale = $locale;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): CurrencyI18n
    {
        $this->name = $name;
        return $this;
    }
}
