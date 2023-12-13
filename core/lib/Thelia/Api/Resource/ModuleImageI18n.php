<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class ModuleImageI18n extends I18n
{
    #[Groups([ModuleImage::GROUP_READ, ModuleImage::GROUP_WRITE])]
    protected ?string $title;

    #[Groups([ModuleImage::GROUP_READ, ModuleImage::GROUP_WRITE])]
    protected ?string $description;

    #[Groups([ModuleImage::GROUP_READ, ModuleImage::GROUP_WRITE])]
    protected ?string $chapo;

    #[Groups([ModuleImage::GROUP_READ, ModuleImage::GROUP_WRITE])]
    protected ?string $postscriptum;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): ModuleImageI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): ModuleImageI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): ModuleImageI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): ModuleImageI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }
}
