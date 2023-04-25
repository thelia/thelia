<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class AttributeI18n extends I18n
{
    #[Groups([Attribute::GROUP_READ, Attribute::GROUP_WRITE])]
    protected string $locale;

    #[Groups([Attribute::GROUP_READ, Attribute::GROUP_WRITE])]
    protected ?string $title;

    #[Groups([Attribute::GROUP_READ, Attribute::GROUP_WRITE])]
    protected ?string $description;

    #[Groups([Attribute::GROUP_READ, Attribute::GROUP_WRITE])]
    protected ?string $chapo;

    #[Groups([Attribute::GROUP_READ, Attribute::GROUP_WRITE])]
    protected ?string $postscriptum;

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): AttributeI18n
    {
        $this->locale = $locale;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): AttributeI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): AttributeI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): AttributeI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): AttributeI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }
}
