<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class FolderImageI18n extends I18n
{
    #[Groups([FolderImage::GROUP_READ, FolderImage::GROUP_WRITE])]
    protected ?string $title;

    #[Groups([FolderImage::GROUP_READ, FolderImage::GROUP_WRITE])]
    protected ?string $description;

    #[Groups([FolderImage::GROUP_READ, FolderImage::GROUP_WRITE])]
    protected ?string $chapo;

    #[Groups([FolderImage::GROUP_READ, FolderImage::GROUP_WRITE])]
    protected ?string $postscriptum;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): FolderImageI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): FolderImageI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): FolderImageI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): FolderImageI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }
}
