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

class ProductUpdateEvent extends ProductCreateEvent
{
    protected $product_id;

    protected $chapo;
    protected $description;
    protected $postscriptum;

    public function __construct($product_id)
    {
        $this->product_id = $product_id;
    }

    public function getProductId()
    {
        return $this->product_id;
    }

    public function setProductId($product_id)
    {
        $this->product_id = $product_id;

        return $this;
    }

    public function getChapo()
    {
        return $this->chapo;
    }

    public function setChapo($chapo)
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    public function getPostscriptum()
    {
        return $this->postscriptum;
    }

    public function setPostscriptum($postscriptum)
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }
}
