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

class FeatureProductUpdateEvent extends FeatureProductEvent
{
    protected $product_id;
    protected $feature_id;
    protected $feature_value;
    protected $is_text_value;

    public function __construct($product_id, $feature_id, $feature_value, $is_text_value = false)
    {
        $this->product_id = $product_id;
        $this->feature_id = $feature_id;
        $this->feature_value = $feature_value;
        $this->is_text_value = $is_text_value;
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

    public function getFeatureId()
    {
        return $this->feature_id;
    }

    public function setFeatureId($feature_id)
    {
        $this->feature_id = $feature_id;

        return $this;
    }

    public function getFeatureValue()
    {
        return $this->feature_value;
    }

    public function setFeatureValue($feature_value)
    {
        $this->feature_value = $feature_value;

        return $this;
    }

    public function getIsTextValue()
    {
        return $this->is_text_value;
    }

    public function setIsTextValue($is_text_value)
    {
        $this->is_text_value = $is_text_value;

        return $this;
    }
}
