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

class ProductSaleElementDeleteEvent extends ProductSaleElementEvent
{
    protected $product_sale_element_id;
    protected $currency_id;

    public function __construct($product_sale_element_id, $currency_id)
    {
        parent::__construct();

        $this->product_sale_element_id = $product_sale_element_id;
        $this->setCurrencyId($currency_id);
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

    public function getCurrencyId()
    {
        return $this->currency_id;
    }

    public function setCurrencyId($currency_id)
    {
        $this->currency_id = $currency_id;

        return $this;
    }

}
