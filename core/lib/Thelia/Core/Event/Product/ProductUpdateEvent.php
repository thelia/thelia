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

namespace Thelia\Core\Event\Product;

class ProductUpdateEvent extends ProductCreateEvent
{
    protected $chapo;
    protected $description;
    protected $postscriptum;
    protected $brand_id;
    protected $virtual_document_id;

    /**
     * @param int $product_id
     */
    public function __construct(protected $product_id)
    {
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

    /**
     * @param int $brand_id
     *
     * @return $this
     */
    public function setBrandId($brand_id)
    {
        $this->brand_id = $brand_id;

        return $this;
    }

    /**
     * @return int
     */
    public function getBrandId()
    {
        return $this->brand_id;
    }

    /**
     * @return $this
     */
    public function setVirtualDocumentId($virtual_document_id)
    {
        $this->virtual_document_id = $virtual_document_id;

        return $this;
    }

    public function getVirtualDocumentId()
    {
        return $this->virtual_document_id;
    }
}
