<?php

declare(strict_types=1);

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

class FeatureProductUpdateEvent extends FeatureProductEvent
{
    protected $is_text_value;

    protected $locale;

    /**
     * @param int  $product_id
     * @param int  $feature_id
     * @param bool $is_text_value
     */
    public function __construct(protected $product_id, protected $feature_id, protected $feature_value, $is_text_value = false)
    {
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
     * @return $this
     */
    public function setProductId($product_id): static
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
     * @return $this
     */
    public function setFeatureId($feature_id): static
    {
        $this->feature_id = $feature_id;

        return $this;
    }

    public function getFeatureValue()
    {
        return $this->feature_value;
    }

    public function setFeatureValue($feature_value): static
    {
        $this->feature_value = $feature_value;

        return $this;
    }

    public function getIsTextValue(): bool
    {
        return (bool) $this->is_text_value;
    }

    public function setIsTextValue($is_text_value): static
    {
        $this->is_text_value = (bool) $is_text_value;

        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setLocale($locale): void
    {
        $this->locale = $locale;
    }
}
