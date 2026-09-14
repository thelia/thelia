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

namespace Thelia\Core\Event\Product;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Product;

abstract class ProductAssociationEvent extends ActionEvent
{
    public function __construct(
        protected Product $product,
        protected int $associatedProductId,
        protected string $typeCode,
        protected bool $applyReciprocity = true,
        protected bool $announceAccessoryEvent = true,
    ) {
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getAssociatedProductId(): int
    {
        return $this->associatedProductId;
    }

    public function getTypeCode(): string
    {
        return $this->typeCode;
    }

    public function appliesReciprocity(): bool
    {
        return $this->applyReciprocity;
    }

    public function announcesAccessoryEvent(): bool
    {
        return $this->announceAccessoryEvent;
    }
}
