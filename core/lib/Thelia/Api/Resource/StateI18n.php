<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class StateI18n extends I18n
{
    #[Groups([State::GROUP_READ])]
    protected string $locale;

    #[Groups([State::GROUP_READ])]
    protected ?string $title;

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): StateI18n
    {
        $this->locale = $locale;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): StateI18n
    {
        $this->title = $title;
        return $this;
    }
}
