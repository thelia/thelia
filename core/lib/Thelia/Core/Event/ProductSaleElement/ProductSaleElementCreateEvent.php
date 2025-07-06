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

use Thelia\Model\Product;

class ProductSaleElementCreateEvent extends ProductSaleElementEvent
{
    protected $product;
    protected $attribute_av_list;
    protected $currency_id;

    public function __construct(Product $product, $attribute_av_list, $currency_id)
    {
        parent::__construct();

        $this->setAttributeAvList($attribute_av_list);
        $this->setCurrencyId($currency_id);
        $this->setProduct($product);
    }

    public function getAttributeAvList()
    {
        return $this->attribute_av_list;
    }

    public function setAttributeAvList($attribute_av_list): static
    {
        $this->attribute_av_list = $attribute_av_list;

        return $this;
    }

    public function getCurrencyId()
    {
        return $this->currency_id;
    }

    public function setCurrencyId($currency_id): static
    {
        $this->currency_id = $currency_id;

        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct($product): static
    {
        $this->product = $product;

        return $this;
    }
}
