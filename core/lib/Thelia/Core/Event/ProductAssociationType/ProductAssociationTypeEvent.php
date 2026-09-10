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

class ProductAssociationTypeEvent extends ActionEvent
{
    public function __construct(protected ?ProductAssociationType $productAssociationType = null)
    {
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
}
