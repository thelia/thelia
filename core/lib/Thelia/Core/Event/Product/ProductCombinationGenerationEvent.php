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

namespace Thelia\Core\Event\Product;

use Thelia\Model\Product;

class ProductCombinationGenerationEvent extends ProductEvent
{
    protected $reference;
    protected $price;
    protected $currency_id;
    protected $weight;
    protected $quantity;
    protected $sale_price;
    protected $onsale;
    protected $isnew;
    protected $ean_code;
    protected $combinations;

    public function __construct(Product $product, $currency_id, $combinations)
    {
        parent::__construct($product);

        $this->setCombinations($combinations);
        $this->setCurrencyId($currency_id);
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

    public function getReference()
    {
        return $this->reference;
    }

    public function setReference($reference)
    {
        $this->reference = $reference;

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

    public function getCombinations()
    {
        return $this->combinations;
    }

    public function setCombinations($combinations)
    {
        $this->combinations = $combinations;

        return $this;
    }
}
