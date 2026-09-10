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

class ProductAssociationTypeDeleteEvent extends ProductAssociationTypeEvent
{
    protected int $productAssociationTypeId;

    public function __construct(int $productAssociationTypeId)
    {
        parent::__construct();

        $this->productAssociationTypeId = $productAssociationTypeId;
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
