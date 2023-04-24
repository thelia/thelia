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


class CategoryI18n extends I18n
{
    #[Groups([Category::GROUP_READ, Category::GROUP_WRITE, Product::GROUP_READ_SINGLE])]
    protected ?string $title;

    #[Groups([Category::GROUP_READ, Category::GROUP_WRITE, Product::GROUP_READ_SINGLE])]
    protected ?string $chapo;

    #[Groups([Category::GROUP_READ, Category::GROUP_WRITE, Product::GROUP_READ_SINGLE])]
    protected ?string $description;

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
}
