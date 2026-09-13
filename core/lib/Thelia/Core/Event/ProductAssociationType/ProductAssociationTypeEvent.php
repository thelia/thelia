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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\ProductAssociationType;

/**
 * An event about one relation type: the type itself once the action has loaded or
 * created it, and, for the events addressing a type that already exists, its id.
 */
class ProductAssociationTypeEvent extends ActionEvent
{
    public function __construct(
        protected ?ProductAssociationType $productAssociationType = null,
        protected ?int $productAssociationTypeId = null,
    ) {
    }

    public function hasProductAssociationType(): bool
    {
        return $this->productAssociationType instanceof ProductAssociationType;
    }

    public function getProductAssociationType(): ?ProductAssociationType
    {
        return $this->productAssociationType;
    }

    public function setProductAssociationType(ProductAssociationType $productAssociationType): static
    {
        $this->productAssociationType = $productAssociationType;

        return $this;
    }

    /**
     * @throws \LogicException when the event names no type yet: a creation that has not run
     */
    public function getProductAssociationTypeId(): int
    {
        return $this->productAssociationTypeId
            ?? $this->productAssociationType?->getId()
            ?? throw new \LogicException('This event names no product association type yet.');
    }

    public function setProductAssociationTypeId(int $productAssociationTypeId): static
    {
        $this->productAssociationTypeId = $productAssociationTypeId;

        return $this;
    }
}
