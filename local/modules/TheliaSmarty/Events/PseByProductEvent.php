<?php

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
