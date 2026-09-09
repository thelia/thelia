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

class SaleI18n extends I18n
{
    #[Groups([Sale::GROUP_ADMIN_READ, Sale::GROUP_FRONT_READ])]
    protected ?string $title = null;

    /**
     * The short badge a theme prints on the products of the operation — "-20%",
     * "Private sale" — as opposed to the title of its page.
     */
    #[Groups([Sale::GROUP_ADMIN_READ, Sale::GROUP_FRONT_READ])]
    protected ?string $saleLabel = null;

    #[Groups([Sale::GROUP_ADMIN_READ, Sale::GROUP_FRONT_READ])]
    protected ?string $chapo = null;

    #[Groups([Sale::GROUP_ADMIN_READ, Sale::GROUP_FRONT_READ])]
    protected ?string $description = null;

    #[Groups([Sale::GROUP_ADMIN_READ, Sale::GROUP_FRONT_READ])]
    protected ?string $postscriptum = null;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getSaleLabel(): ?string
    {
        return $this->saleLabel;
    }

    public function setSaleLabel(?string $saleLabel): self
    {
        $this->saleLabel = $saleLabel;

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
        return $this->postscriptum;
    }

    public function setPostscriptum(?string $postscriptum): self
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }
}
