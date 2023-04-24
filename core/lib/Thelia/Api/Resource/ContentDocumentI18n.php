<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class ContentDocumentI18n extends I18n
{
    #[Groups([ContentDocument::GROUP_READ, ContentDocument::GROUP_WRITE])]
    protected string $locale;

    #[Groups([ContentDocument::GROUP_READ, ContentDocument::GROUP_WRITE])]
    protected ?string $title;

    #[Groups([ContentDocument::GROUP_READ, ContentDocument::GROUP_WRITE])]
    protected ?string $description;

    #[Groups([ContentDocument::GROUP_READ, ContentDocument::GROUP_WRITE])]
    protected ?string $chapo;

    #[Groups([ContentDocument::GROUP_READ, ContentDocument::GROUP_WRITE])]
    protected ?string $postscriptum;

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): ContentDocumentI18n
    {
        $this->locale = $locale;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): ContentDocumentI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): ContentDocumentI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): ContentDocumentI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): ContentDocumentI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }
}
