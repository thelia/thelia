<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class FolderDocumentI18n extends  I18n
{
    #[Groups([I18n::GROUP_READ])]
    protected string $locale;

    #[Groups([I18n::GROUP_READ])]
    protected ?string $title;

    #[Groups([I18n::GROUP_READ])]
    protected ?string $description;

    #[Groups([I18n::GROUP_READ])]
    protected ?string $chapo;

    #[Groups([I18n::GROUP_READ])]
    protected ?string $postscriptum;

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): FolderDocumentI18n
    {
        $this->locale = $locale;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): FolderDocumentI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): FolderDocumentI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): FolderDocumentI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): FolderDocumentI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }
}
