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
     *
     * @param int $product_sale_element_id
     * @param int $currency_id
     */
    public function __construct(protected $product_sale_element_id, protected $currency_id)
    {
        parent::__construct();
    }

    /**
     * @return int
     */
    public function getProductSaleElementId()
    {
        return $this->product_sale_element_id;
    }

    /**
     * @param int $product_sale_element_id
     *
     * @return $this
     */
    public function setProductSaleElementId($product_sale_element_id): self
    {
        $this->product_sale_element_id = $product_sale_element_id;

        return $this;
    }

    /**
     * @return int
     */
    public function getCurrencyId()
    {
        return $this->currency_id;
    }

    /**
     * @param int $currency_id
     *
     * @return $this
     */
    public function setCurrencyId($currency_id): self
    {
        $this->currency_id = $currency_id;

        return $this;
    }
}
