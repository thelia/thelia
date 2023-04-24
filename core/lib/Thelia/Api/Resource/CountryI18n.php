<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class CountryI18n extends I18n
{
    #[Groups([Country::GROUP_READ])]
    protected string $locale;

    #[Groups([Country::GROUP_READ])]
    protected ?string $title;

    #[Groups([Country::GROUP_READ])]
    protected ?string $description;

    #[Groups([Country::GROUP_READ])]
    protected ?string $chapo;

    #[Groups([Country::GROUP_READ])]
    protected ?string $postscriptum;

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): CountryI18n
    {
        $this->locale = $locale;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): CountryI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): CountryI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): CountryI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): CountryI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }
}
