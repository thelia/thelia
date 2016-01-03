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
