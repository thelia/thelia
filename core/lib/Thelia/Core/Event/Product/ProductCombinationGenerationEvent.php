<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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
