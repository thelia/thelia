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

namespace Thelia\Core\Event\Sale;

/**
 * Class SaleUpdateEvent
 * @package Thelia\Core\Event\Sale
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class SaleUpdateEvent extends SaleCreateEvent
{
    protected $saleId;

    protected $chapo;
    protected $description;
    protected $postscriptum;
    protected $active;
    protected $startDate;
    protected $endDate;
    protected $priceOffsetType;
    protected $displayInitialPrice;
    protected $priceOffsets;
    protected $products;
    protected $productAttributes;

    /**
     * @param int $saleId
     */
    public function __construct($saleId)
    {
        $this->saleId = $saleId;
    }

    /**
     * @param int $saleId
     *
     * @return SaleUpdateEvent $this
     */
    public function setSaleId($saleId)
    {
        $this->saleId = $saleId;

        return $this;
    }

    /**
     * @return int
     */
    public function getSaleId()
    {
        return $this->saleId;
    }

    /**
     * @param string $chapo
     *
     * @return SaleUpdateEvent $this
     */
    public function setChapo($chapo)
    {
        $this->chapo = $chapo;

        return $this;
    }

    /**
     * @return string
     */
    public function getChapo()
    {
        return $this->chapo;
    }
    /**
     * @param string $description
     *
     * @return SaleUpdateEvent $this
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $postscriptum
     *
     * @return SaleUpdateEvent $this
     */
    public function setPostscriptum($postscriptum)
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    /**
     * @return string
     */
    public function getPostscriptum()
    {
        return $this->postscriptum;
    }

    /**
     * @param  bool            $active
     * @return SaleUpdateEvent $this
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return bool
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * @param  \DateTime       $endDate
     * @return SaleUpdateEvent $this
     */
    public function setEndDate($endDate)
    {
        $this->endDate = $endDate;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * @param  int             $priceOffsetType
     * @return SaleUpdateEvent $this
     */
    public function setPriceOffsetType($priceOffsetType)
    {
        $this->priceOffsetType = $priceOffsetType;

        return $this;
    }

    /**
     * @return int
     */
    public function getPriceOffsetType()
    {
        return $this->priceOffsetType;
    }

    /**
     * @param  \DateTime       $startDate
     * @return SaleUpdateEvent $this
     */
    public function setStartDate($startDate)
    {
        $this->startDate = $startDate;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @param  bool  $displayInitialPrice
     * @return $this
     */
    public function setDisplayInitialPrice($displayInitialPrice)
    {
        $this->displayInitialPrice = $displayInitialPrice;

        return $this;
    }

    /**
     * @return bool
     */
    public function getDisplayInitialPrice()
    {
        return $this->displayInitialPrice;
    }

    /**
     * @param  array $priceOffsets an array of (currency_id => price offset) couples.
     * @return $this
     */
    public function setPriceOffsets($priceOffsets)
    {
        $this->priceOffsets = $priceOffsets;

        return $this;
    }

    /**
     * @return array
     */
    public function getPriceOffsets()
    {
        return $this->priceOffsets;
    }

    /**
     * @param  array $products an array of (product_id => product_sale_elements ids[])
     * @return $this
     */
    public function setProducts($products)
    {
        $this->products = $products;

        return $this;
    }

    /**
     * @return array
     */
    public function getProducts()
    {
        return $this->products;
    }

    /**
     * @param  array $productAttributes an array of (product_id => array of attribute IDs)
     * @return $this
     */
    public function setProductAttributes($productAttributes)
    {
        $this->productAttributes = $productAttributes;

        return $this;
    }

    /**
     * @return array $productAttributes an array of (product_id => array of attribute IDs)
     */
    public function getProductAttributes()
    {
        return $this->productAttributes;
    }

}
