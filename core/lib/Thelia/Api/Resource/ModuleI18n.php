<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class ModuleI18n extends I18n
{
    #[Groups([Module::GROUP_READ_SINGLE, Module::GROUP_WRITE ,I18n::GROUP_READ,Order::GROUP_READ_SINGLE, Order::GROUP_WRITE])]
    protected string $locale;

    #[Groups([Module::GROUP_READ_SINGLE, Module::GROUP_WRITE ,I18n::GROUP_READ,Order::GROUP_READ_SINGLE, Order::GROUP_WRITE])]
    protected ?string $title;

    #[Groups([Module::GROUP_READ_SINGLE, Module::GROUP_WRITE])]
    protected ?string $description;

    #[Groups([Module::GROUP_READ_SINGLE, Module::GROUP_WRITE])]
    protected ?string $chapo;

    #[Groups([Module::GROUP_READ_SINGLE, Module::GROUP_WRITE])]
    protected ?string $postscriptum;

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): ModuleI18n
    {
        $this->locale = $locale;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): ModuleI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): ModuleI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): ModuleI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): ModuleI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }
}
