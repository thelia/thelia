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

class ProductI18n extends I18n
{
    #[Groups([Product::GROUP_ADMIN_READ, Product::GROUP_FRONT_READ,  Product::GROUP_ADMIN_WRITE])]
    protected ?string $title;

    #[Groups([Product::GROUP_ADMIN_READ, Product::GROUP_FRONT_READ, Product::GROUP_ADMIN_WRITE])]
    protected ?string $chapo;

    #[Groups([Product::GROUP_ADMIN_READ, Product::GROUP_FRONT_READ, Product::GROUP_ADMIN_WRITE])]
    protected ?string $description;

    #[Groups([Product::GROUP_ADMIN_READ, Product::GROUP_FRONT_READ, Product::GROUP_ADMIN_WRITE])]
    protected ?string $postscriptum;

    #[Groups([Product::GROUP_ADMIN_READ, Product::GROUP_FRONT_READ, Product::GROUP_ADMIN_WRITE])]
    protected ?string $metaTitle;

    #[Groups([Product::GROUP_ADMIN_READ, Product::GROUP_FRONT_READ, Product::GROUP_ADMIN_WRITE])]
    protected ?string $metaDescription;

    #[Groups([Product::GROUP_ADMIN_READ, Product::GROUP_FRONT_READ, Product::GROUP_ADMIN_WRITE])]
    protected ?string $metaKeywords;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getChapo(): string
    {
        return $this->chapo;
    }

    public function setChapo(?string $chapo): self
    {
        $this->chapo = $chapo;

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

    public function setMetaTitle(?string $meta_title): self
    {
        $this->metaTitle = $meta_title;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $meta_description): self
    {
        $this->metaDescription = $meta_description;

        return $this;
    }

    public function getMetaKeywords(): ?string
    {
        return $this->metaKeywords;
    }

    public function setMetaKeywords(?string $meta_keywords): self
    {
        $this->metaKeywords = $meta_keywords;

        return $this;
    }
}
