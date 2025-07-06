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

class ProductDeleteEvent extends ProductEvent
{
    /**
     * @param int $product_id
     */
    public function __construct(protected $product_id)
    {
    }

    /**
     * @return int
     */
    public function getProductId()
    {
        return $this->product_id;
    }

    /**
     * @return $this
     */
    public function setProductId($product_id): static
    {
        $this->product_id = $product_id;

        return $this;
    }
}
