<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class ProductDocumentI18n extends I18n
{
    #[Groups([ProductDocument::GROUP_READ, ProductDocument::GROUP_WRITE])]
    protected ?string $title;

    #[Groups([ProductDocument::GROUP_READ, ProductDocument::GROUP_WRITE])]
    protected ?string $description;

    #[Groups([ProductDocument::GROUP_READ, ProductDocument::GROUP_WRITE])]
    protected ?string $chapo;

    #[Groups([ProductDocument::GROUP_READ, ProductDocument::GROUP_WRITE])]
    protected ?string $postscriptum;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): ProductDocumentI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): ProductDocumentI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): ProductDocumentI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): ProductDocumentI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }
}
