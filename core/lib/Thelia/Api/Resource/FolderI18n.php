<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Api\Resource;

use Symfony\Component\Serializer\Annotation\Groups;

class FolderI18n extends I18n
{
    #[Groups([Folder::GROUP_ADMIN_READ, Folder::GROUP_ADMIN_WRITE])]
    protected ?string $title;

    #[Groups([Folder::GROUP_ADMIN_READ, Folder::GROUP_ADMIN_WRITE])]
    protected ?string $description;

    #[Groups([Folder::GROUP_ADMIN_READ, Folder::GROUP_ADMIN_WRITE])]
    protected ?string $chapo;

    #[Groups([Folder::GROUP_ADMIN_READ, Folder::GROUP_ADMIN_WRITE])]
    protected ?string $postscriptum;

    #[Groups([Folder::GROUP_ADMIN_READ, Folder::GROUP_ADMIN_WRITE])]
    protected ?string $metaTitle;

    #[Groups([Folder::GROUP_ADMIN_READ, Folder::GROUP_ADMIN_WRITE])]
    protected ?string $metaDescription;

    #[Groups([Folder::GROUP_ADMIN_READ, Folder::GROUP_ADMIN_WRITE])]
    protected ?string $metaKeywords;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getChapo(): ?string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): self
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getPostscriptum(): ?string
    {
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): self
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): self
    {
        $this->metaTitle = $metaTitle;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): self
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }

    public function getMetaKeywords(): ?string
    {
        return $this->metaKeywords;
    }

    public function setMetaKeywords(?string $metaKeywords): self
    {
        $this->metaKeywords = $metaKeywords;

        return $this;
    }
}
