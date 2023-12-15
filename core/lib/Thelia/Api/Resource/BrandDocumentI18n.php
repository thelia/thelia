<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class BrandDocumentI18n extends I18n
{
    #[Groups([ContentDocument::GROUP_READ, ContentDocument::GROUP_WRITE])]
    protected ?string $title;

    #[Groups([ContentDocument::GROUP_READ, ContentDocument::GROUP_WRITE])]
    protected ?string $description;

    #[Groups([ContentDocument::GROUP_READ, ContentDocument::GROUP_WRITE])]
    protected ?string $chapo;

    #[Groups([ContentDocument::GROUP_READ, ContentDocument::GROUP_WRITE])]
    protected ?string $postscriptum;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): BrandDocumentI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): BrandDocumentI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): BrandDocumentI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): BrandDocumentI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }
}
