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

    public function setAttributeAvList($attribute_av_list)
    {
        $this->attribute_av_list = $attribute_av_list;

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

    public function getProduct()
    {
      return $this->product;
    }

    public function setProduct($product)
    {
      $this->product = $product;

      return $this;
    }

}
