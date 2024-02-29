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

namespace TheliaSmarty\Events;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\ProductSaleElements;

class PseByProductEvent extends ActionEvent
{
    protected ProductSaleElements $productSaleElements;

    public function __construct($productSaleElements)
    {
        $this->productSaleElements = $productSaleElements;
    }

    public function getProductSaleElements(): ProductSaleElements
    {
        return $this->productSaleElements;
    }
}
