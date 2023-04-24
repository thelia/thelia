<?php

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class FolderI18n extends I18n
{
    #[Groups([Folder::GROUP_READ, Folder::GROUP_WRITE])]
    protected string $locale;

    #[Groups([Folder::GROUP_READ, Folder::GROUP_WRITE])]
    protected ?string $title;

    #[Groups([Folder::GROUP_READ, Folder::GROUP_WRITE])]
    protected ?string $description;

    #[Groups([Folder::GROUP_READ, Folder::GROUP_WRITE])]
    protected ?string $chapo;

    #[Groups([Folder::GROUP_READ, Folder::GROUP_WRITE])]
    protected ?string $postscriptum;

    #[Groups([Folder::GROUP_READ, Folder::GROUP_WRITE])]
    protected ?string $metaTitle;

    #[Groups([Folder::GROUP_READ, Folder::GROUP_WRITE])]
    protected ?string $metaDescription;

    #[Groups([Folder::GROUP_READ, Folder::GROUP_WRITE])]
    protected ?string $metaKeywords;

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): FolderI18n
    {
        $this->locale = $locale;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): FolderI18n
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): FolderI18n
    {
        $this->description = $description;
        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): FolderI18n
    {
        $this->chapo = $chapo;
        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): FolderI18n
    {
        $this->postscriptum = $postscriptum;
        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): FolderI18n
    {
        $this->metaTitle = $metaTitle;
        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): FolderI18n
    {
        $this->metaDescription = $metaDescription;
        return $this;
    }

    public function getMetaKeywords(): ?string
    {
        return $this->metaKeywords;
    }

    public function setMetaKeywords(?string $metaKeywords): FolderI18n
    {
        $this->metaKeywords = $metaKeywords;
        return $this;
    }
}
