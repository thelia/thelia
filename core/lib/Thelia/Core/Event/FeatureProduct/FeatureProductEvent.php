<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
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
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Event\FeatureProduct;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\FeatureProduct;

class FeatureProductEvent extends ActionEvent
{
    protected $featureProduct = null;

    public function __construct(FeatureProduct $featureProduct = null)
    {
        $this->featureProduct = $featureProduct;
    }

    public function hasFeatureProduct()
    {
        return ! is_null($this->featureProduct);
    }

    public function getFeatureProduct()
    {
        return $this->featureProduct;
    }

    public function setFeatureProduct($featureProduct)
    {
        $this->featureProduct = $featureProduct;

        return $this;
    }
}
