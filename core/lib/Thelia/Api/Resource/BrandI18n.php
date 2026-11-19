<?php

declare(strict_types=1);

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

class BrandI18n extends I18n
{
    #[Groups([
        Brand::GROUP_ADMIN_READ,
        Brand::GROUP_FRONT_READ,
        Product::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_FRONT_READ_SINGLE,
    ])]
    protected ?string $title = null;

    #[Groups([
        Brand::GROUP_ADMIN_READ,
        Brand::GROUP_FRONT_READ,
        Product::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_FRONT_READ_SINGLE,
    ])]
    protected ?string $chapo = null;

    #[Groups([
        Brand::GROUP_ADMIN_READ,
        Brand::GROUP_FRONT_READ,
        Product::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_FRONT_READ_SINGLE,
    ])]
    protected ?string $description = null;

    /**
     * The text a brand page shows under its products, as `CategoryI18n::$postscriptum` does for a category.
     * Left uninitialized until it is set: the Propel bridge writes every initialized property of a
     * translation, so a PATCH that leaves it out must not empty the stored text.
     */
    #[Groups([Brand::GROUP_ADMIN_READ, Brand::GROUP_FRONT_READ])]
    protected ?string $postscriptum;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

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
        return $this->postscriptum ?? null;
    }

    public function setPostscriptum(?string $postscriptum): self
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }
}
