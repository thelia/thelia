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

class ModuleI18n extends I18n
{
    #[Groups([Module::GROUP_ADMIN_READ_SINGLE, Module::GROUP_ADMIN_WRITE, Module::GROUP_ADMIN_READ, Order::GROUP_ADMIN_READ_SINGLE, Order::GROUP_ADMIN_WRITE])]
    protected ?string $title;

    #[Groups([Module::GROUP_ADMIN_READ_SINGLE, Module::GROUP_ADMIN_WRITE])]
    protected ?string $description;

    #[Groups([Module::GROUP_ADMIN_READ_SINGLE, Module::GROUP_ADMIN_WRITE])]
    protected ?string $chapo;

    #[Groups([Module::GROUP_ADMIN_READ_SINGLE, Module::GROUP_ADMIN_WRITE])]
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
