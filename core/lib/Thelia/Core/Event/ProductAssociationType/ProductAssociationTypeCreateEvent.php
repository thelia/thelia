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

namespace Thelia\Core\Event\ProductAssociationType;

class ProductAssociationTypeCreateEvent extends ProductAssociationTypeEvent
{
    protected string $locale = '';
    protected string $code = '';
    protected string $title = '';
    protected ?string $description = null;
    protected int $visible = 1;
    protected int $reciprocal = 0;

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getVisible(): int
    {
        return $this->visible;
    }

    public function setVisible(int $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function getReciprocal(): int
    {
        return $this->reciprocal;
    }

    public function setReciprocal(int $reciprocal): static
    {
        $this->reciprocal = $reciprocal;

        return $this;
    }
}
