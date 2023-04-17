<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class CurrencyI18n extends I18n
{
    #[Groups([I18n::GROUP_READ])]
    protected string $locale;

    #[Groups([I18n::GROUP_READ])]
    protected ?string $name;

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
