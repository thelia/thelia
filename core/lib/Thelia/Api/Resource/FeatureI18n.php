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

class FeatureI18n extends I18n
{
    #[Groups([
        Feature::GROUP_ADMIN_READ,
        Feature::GROUP_FRONT_READ,
        Feature::GROUP_ADMIN_WRITE,
        Product::GROUP_ADMIN_READ_SINGLE,
        Product::GROUP_FRONT_READ_SINGLE,
    ])]
    protected ?string $title;

    #[Groups([
        Feature::GROUP_ADMIN_READ,
        Feature::GROUP_FRONT_READ,
        Feature::GROUP_ADMIN_WRITE,
    ])]
    protected ?string $description;

    #[Groups([
        Feature::GROUP_ADMIN_READ,
        Feature::GROUP_FRONT_READ,
        Feature::GROUP_ADMIN_WRITE,
    ])]
    protected ?string $chapo;

    #[Groups([
        Feature::GROUP_ADMIN_READ,
        Feature::GROUP_FRONT_READ,
        Feature::GROUP_ADMIN_WRITE,
    ])]
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
}
