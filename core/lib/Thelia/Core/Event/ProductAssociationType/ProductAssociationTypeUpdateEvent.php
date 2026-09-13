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
 * reciprocity and the other languages where they were. The title and the
 * description are carried one by one too: correcting a heading says nothing about
 * the paragraph printed under it. The action reads the `carries*()` methods to
 * know which part of the type the event is about.
 */
class ProductAssociationTypeUpdateEvent extends ProductAssociationTypeCreateEvent
{
    private bool $carriesTitle = false;
    private bool $carriesDescription = false;
    private bool $carriesVisible = false;
    private bool $carriesReciprocal = false;

    public function __construct(int $productAssociationTypeId)
    {
        parent::__construct(productAssociationTypeId: $productAssociationTypeId);
    }

    public function setTitle(string $title): static
    {
        $this->carriesTitle = true;

        return parent::setTitle($title);
    }

    public function carriesTitle(): bool
    {
        return $this->carriesTitle;
    }

    public function setDescription(?string $description): static
    {
        $this->carriesDescription = true;

        return parent::setDescription($description);
    }

    public function carriesDescription(): bool
    {
        return $this->carriesDescription;
    }

    public function carriesWording(): bool
    {
        return $this->carriesTitle || $this->carriesDescription;
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
}
