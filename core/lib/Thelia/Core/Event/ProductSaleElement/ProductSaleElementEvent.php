<?php

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
    public $product_sale_element;

    public function __construct(ProductSaleElements $product_sale_element = null)
    {
        $this->product_sale_element = $product_sale_element;
    }

    public function hasProductSaleElement()
    {
        return ! \is_null($this->product_sale_element);
    }

    public function getProductSaleElement()
    {
        return $this->product_sale_element;
    }

    public function setProductSaleElement(ProductSaleElements $product_sale_element)
    {
        $this->product_sale_element = $product_sale_element;

        return $this;
    }
}
