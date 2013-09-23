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

namespace Thelia\Core\Event;

use Thelia\Model\Product;
class ProductCreateCombinationEvent extends ProductEvent
{
    protected $use_default_pricing;
    protected $attribute_av_list;

    public function __construct(Product $product, $use_default_pricing, $attribute_av_list)
    {
        parent::__construct($product);

        $this->use_default_pricing = $use_default_pricing;
        $this->attribute_av_list = $attribute_av_list;
    }

    public function getUseDefaultPricing()
    {
        return $this->use_default_pricing;
    }

    public function setUseDefaultPricing($use_default_pricing)
    {
        $this->use_default_pricing = $use_default_pricing;

        return $this;
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
}
