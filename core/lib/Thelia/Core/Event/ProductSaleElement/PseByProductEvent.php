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

/**
 * Dispatched for each product sale element hydrated when listing the PSEs of
 * a product, so modules can decorate it (e.g. override the price virtual
 * columns) before it is rendered.
 */
class PseByProductEvent extends ActionEvent
{
    public function __construct(protected ProductSaleElements $productSaleElements)
    {
    }

    public function getProductSaleElements(): ProductSaleElements
    {
        return $this->productSaleElements;
    }
}
