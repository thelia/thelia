<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class StateI18n extends I18n
{
    #[Groups([I18n::GROUP_READ])]
    protected string $locale;

    #[Groups([I18n::GROUP_READ])]
    protected ?string $title;

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

}
