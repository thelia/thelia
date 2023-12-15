<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class BrandImageI18n extends I18n
{
    #[Groups([BrandImage::GROUP_READ, BrandImage::GROUP_WRITE])]
    protected ?string $title;

    #[Groups([BrandImage::GROUP_READ, BrandImage::GROUP_WRITE])]
    protected ?string $description;

    #[Groups([BrandImage::GROUP_READ, BrandImage::GROUP_WRITE])]
    protected ?string $chapo;

    #[Groups([BrandImage::GROUP_READ, BrandImage::GROUP_WRITE])]
    protected ?string $postscriptum;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): BrandImageI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): BrandImageI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): BrandImageI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): BrandImageI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }

}
