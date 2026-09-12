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

/**
 * Unlike a creation, an update carries only what its caller set: the code is never
 * written back, and a wording sent in one language leaves the visibility, the
 * reciprocity and the other languages where they were. The action reads the
 * `carries*()` methods to know which part of the type the event is about.
 */
class ProductAssociationTypeUpdateEvent extends ProductAssociationTypeCreateEvent
{
    protected int $productAssociationTypeId;

    private bool $carriesWording = false;
    private bool $carriesVisible = false;
    private bool $carriesReciprocal = false;

    public function __construct(int $productAssociationTypeId)
    {
        parent::__construct();

        $this->productAssociationTypeId = $productAssociationTypeId;
    }

    public function setTitle(string $title): static
    {
        $this->carriesWording = true;

        return parent::setTitle($title);
    }

    public function setDescription(?string $description): static
    {
        $this->carriesWording = true;

        return parent::setDescription($description);
    }

    public function carriesWording(): bool
    {
        return $this->carriesWording;
    }

    public function setVisible(int $visible): static
    {
        $this->carriesVisible = true;

        return parent::setVisible($visible);
    }

    public function carriesVisible(): bool
    {
        return $this->carriesVisible;
    }

    public function setReciprocal(int $reciprocal): static
    {
        $this->carriesReciprocal = true;

        return parent::setReciprocal($reciprocal);
    }

    public function carriesReciprocal(): bool
    {
        return $this->carriesReciprocal;
    }

    public function getProductAssociationTypeId(): int
    {
        return $this->productAssociationTypeId;
    }

    public function setProductAssociationTypeId(int $productAssociationTypeId): static
    {
        $this->productAssociationTypeId = $productAssociationTypeId;

        return $this;
    }
}
