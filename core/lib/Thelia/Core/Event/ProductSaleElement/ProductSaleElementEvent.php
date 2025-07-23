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

namespace Thelia\Core\Event\ProductSaleElement;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\ProductSaleElements;

class ProductSaleElementEvent extends ActionEvent
{
    public function __construct(public ?ProductSaleElements $product_sale_element = null)
    {
    }

    public function hasProductSaleElement(): bool
    {
        return $this->product_sale_element instanceof ProductSaleElements;
    }

    public function getProductSaleElement(): ?ProductSaleElements
    {
        return $this->product_sale_element;
    }

    public function setProductSaleElement(ProductSaleElements $product_sale_element): static
    {
        $this->product_sale_element = $product_sale_element;

        return $this;
    }
}
