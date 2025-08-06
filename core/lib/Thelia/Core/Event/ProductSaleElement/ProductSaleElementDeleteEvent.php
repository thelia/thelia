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

class ProductSaleElementDeleteEvent extends ProductSaleElementEvent
{
    /**
     * ProductSaleElementDeleteEvent constructor.
     */
    public function __construct(protected int $product_sale_element_id, protected int $currency_id)
    {
        parent::__construct();
    }

    public function getProductSaleElementId(): int
    {
        return $this->product_sale_element_id;
    }

    /**
     * @return $this
     */
    public function setProductSaleElementId(int $product_sale_element_id): self
    {
        $this->product_sale_element_id = $product_sale_element_id;

        return $this;
    }

    public function getCurrencyId(): int
    {
        return $this->currency_id;
    }

    /**
     * @return $this
     */
    public function setCurrencyId(int $currency_id): self
    {
        $this->currency_id = $currency_id;

        return $this;
    }
}
