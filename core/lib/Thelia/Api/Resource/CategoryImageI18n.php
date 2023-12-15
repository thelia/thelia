<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class CategoryImageI18n extends I18n
{
    #[Groups([ProductImage::GROUP_READ, ProductImage::GROUP_WRITE])]
    protected ?string $title;

    #[Groups([ProductImage::GROUP_READ, ProductImage::GROUP_WRITE])]
    protected ?string $description;

    #[Groups([ProductImage::GROUP_READ, ProductImage::GROUP_WRITE])]
    protected ?string $chapo;

    #[Groups([ProductImage::GROUP_READ, ProductImage::GROUP_WRITE])]
    protected ?string $postscriptum;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): CategoryImageI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): CategoryImageI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): CategoryImageI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): CategoryImageI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }
}
