<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Event\ProductSaleElement;

use Thelia\Model\Product;

class ProductSaleElementUpdateEvent extends ProductSaleElementEvent
{
    protected $product_sale_element_id;

    protected $product;
    protected $reference;
    protected $price;
    protected $currency_id;
    protected $weight;
    protected $quantity;
    protected $sale_price;
    protected $onsale;
    protected $isnew;
    protected $isdefault;
    protected $ean_code;
    protected $tax_rule_id;
    protected $from_default_currency;

    public function __construct(Product $product, $product_sale_element_id)
    {
        parent::__construct();

        $this->setProduct($product);

        $this->setProductSaleElementId($product_sale_element_id);
    }

    public function getProductSaleElementId()
    {
        return $this->product_sale_element_id;
    }

    public function setProductSaleElementId($product_sale_element_id)
    {
        $this->product_sale_element_id = $product_sale_element_id;

        return $this;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    public function getCurrencyId()
    {
        return $this->currency_id;
    }

    public function setCurrencyId($currency_id)
    {
        $this->currency_id = $currency_id;

        return $this;
    }

    public function getWeight()
    {
        return $this->weight;
    }

    public function setWeight($weight)
    {
        $this->weight = $weight;

        return $this;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getSalePrice()
    {
        return $this->sale_price;
    }

    public function setSalePrice($sale_price)
    {
        $this->sale_price = $sale_price;

        return $this;
    }

    public function getOnsale()
    {
        return $this->onsale;
    }

    public function setOnsale($onsale)
    {
        $this->onsale = $onsale;

        return $this;
    }

    public function getIsnew()
    {
        return $this->isnew;
    }

    public function setIsnew($isnew)
    {
        $this->isnew = $isnew;

        return $this;
    }

    public function getEanCode()
    {
        return $this->ean_code;
    }

    public function setEanCode($ean_code)
    {
        $this->ean_code = $ean_code;

        return $this;
    }

    public function getIsdefault()
    {
        return $this->isdefault;
    }

    public function setIsdefault($isdefault)
    {
        $this->isdefault = $isdefault;

        return $this;
    }

    public function getReference()
    {
        return $this->reference;
    }

    public function setReference($reference)
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * @return Product
     */
    public function getProduct()
    {
        return $this->product;
    }

    public function setProduct($product)
    {
        $this->product = $product;

        return $this;
    }

    public function getTaxRuleId()
    {
        return $this->tax_rule_id;
    }

    public function setTaxRuleId($tax_rule_id)
    {
        $this->tax_rule_id = $tax_rule_id;

        return $this;
    }

    public function getFromDefaultCurrency()
    {
        return $this->from_default_currency;
    }

    public function setFromDefaultCurrency($from_default_currency)
    {
        $this->from_default_currency = $from_default_currency;

        return $this;
    }
}
