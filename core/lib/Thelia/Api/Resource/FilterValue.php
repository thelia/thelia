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

use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource]
class FilterValue
{
    private ?int $mainId = null;

    private ?string $mainTitle = null;

    #[Groups([Filter::GROUP_FRONT_READ])]
    private int $id;

    #[Groups([Filter::GROUP_FRONT_READ])]
    private string $title;

    #[Groups([Filter::GROUP_FRONT_READ])]
    private ?int $depth = null;

    public function getDepth(): ?int
    {
        return $this->depth;
    }

    public function setDepth(?int $depth): self
    {
        $this->depth = $depth;
        return $this;
    }
    public function getMainId(): ?int
    {
        return $this->mainId;
    }

    public function setMainId(?int $mainId): self
    {
        $this->mainId = $mainId;

        return $this;
    }

    public function getMainTitle(): ?string
    {
        return $this->mainTitle;
    }

    public function setMainTitle(?string $mainTitle): self
    {
        $this->mainTitle = $mainTitle;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): FilterValue
    {
        $this->id = $id;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }
}
