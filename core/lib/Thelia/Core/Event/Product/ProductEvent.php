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

namespace Thelia\Core\Event\Product;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Product;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\ProductEvent
 */
class ProductEvent extends ActionEvent
{
    public $product;

    public function __construct(Product $product = null)
    {
        $this->product = $product;
    }

    public function hasProduct()
    {
        return ! \is_null($this->product);
    }

    public function getProduct()
    {
        return $this->product;
    }

    public function setProduct(Product $product)
    {
        $this->product = $product;

        return $this;
    }
}
