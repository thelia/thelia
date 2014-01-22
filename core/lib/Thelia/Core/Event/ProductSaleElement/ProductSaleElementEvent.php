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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\ProductSaleElements;

class ProductSaleElementEvent extends ActionEvent
{
    public $product_sale_element = null;

    public function __construct(ProductSaleElement $product_sale_element = null)
    {
        $this->product_sale_element = $product_sale_element;
    }

    public function hasProductSaleElement()
    {
        return ! is_null($this->product_sale_element);
    }

    public function getProductSaleElement()
    {
        return $this->product_sale_element;
    }

    public function setProductSaleElement(ProductSaleElements $product_sale_element)
    {
        $this->product_sale_element = $product_sale_element;

        return $this;
    }
}
