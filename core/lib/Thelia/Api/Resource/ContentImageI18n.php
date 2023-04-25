<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class ContentImageI18n extends I18n
{
    #[Groups([ContentImage::GROUP_READ, ContentImage::GROUP_WRITE])]
    protected ?string $title;

    #[Groups([ContentImage::GROUP_READ, ContentImage::GROUP_WRITE])]
    protected ?string $description;

    #[Groups([ContentImage::GROUP_READ, ContentImage::GROUP_WRITE])]
    protected ?string $chapo;

    #[Groups([ContentImage::GROUP_READ, ContentImage::GROUP_WRITE])]
    protected ?string $postscriptum;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): ContentImageI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): ContentImageI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): ContentImageI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): ContentImageI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }
}
