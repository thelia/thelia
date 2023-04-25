<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class FeatureAvI18n extends I18n
{
    #[Groups([FeatureAv::GROUP_READ, FeatureAv::GROUP_WRITE])]
    protected string $locale;

    #[Groups([FeatureAv::GROUP_READ, FeatureAv::GROUP_WRITE])]
    protected ?string $title;

    #[Groups([FeatureAv::GROUP_READ, FeatureAv::GROUP_WRITE])]
    protected ?string $description;

    #[Groups([FeatureAv::GROUP_READ, FeatureAv::GROUP_WRITE])]
    protected ?string $chapo;

    #[Groups([FeatureAv::GROUP_READ, FeatureAv::GROUP_WRITE])]
    protected ?string $postscriptum;

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): FeatureAvI18n
    {
        $this->locale = $locale;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): FeatureAvI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): FeatureAvI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): FeatureAvI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): FeatureAvI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }
}
