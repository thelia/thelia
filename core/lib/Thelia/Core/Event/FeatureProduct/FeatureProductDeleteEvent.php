<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event\FeatureProduct;

class FeatureProductDeleteEvent extends FeatureProductEvent
{
    /** @var int */
    protected $product_id;

    /** @var int */
    protected $feature_id;

    /**
     * FeatureProductDeleteEvent constructor.
     *
     * @param int $product_id
     * @param int $feature_id
     */
    public function __construct($product_id, $feature_id)
    {
        parent::__construct(null);

        $this->product_id = $product_id;
        $this->feature_id = $feature_id;
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
}
