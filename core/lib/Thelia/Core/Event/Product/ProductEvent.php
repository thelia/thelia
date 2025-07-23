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

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\ProductEvent
 */
class ProductEvent extends ActionEvent
{
    public function __construct(public ?Product $product = null)
    {
    }

    public function hasProduct(): bool
    {
        return $this->product instanceof Product;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;

        return $this;
    }
}
