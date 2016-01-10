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
    /** @var int */
    protected $product_id;

    /** @var int */
    protected $feature_id;

    protected $feature_value;
    protected $is_text_value;
    protected $locale;

    /**
     * @param int $product_id
     * @param int $feature_id
     * @param $feature_value
     * @param bool $is_text_value
     */
    public function __construct($product_id, $feature_id, $feature_value, $is_text_value = false)
    {
        $this->product_id = $product_id;
        $this->feature_id = $feature_id;
        $this->feature_value = $feature_value;
        $this->setIsTextValue($is_text_value);
    }

    /**
     * @return int the product id
     */
    public function getProductId()
    {
        return $this->product_id;
    }

    /**
     * @param $product_id
     * @return $this
     */
    public function setProductId($product_id)
    {
        $this->product_id = $product_id;

        return $this;
    }

    /**
     * @return int
     */
    public function getFeatureId()
    {
        return $this->feature_id;
    }

    /**
     * @param $feature_id
     * @return $this
     */
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
        return (bool)$this->is_text_value;
    }

    public function setIsTextValue($is_text_value)
    {
        $this->is_text_value = (bool)$is_text_value;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;
    }
}
