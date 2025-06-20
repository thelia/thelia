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
namespace Thelia\Core\Event\Sale;


use Propel\Runtime\Util\PropelDateTime;

/**
 * Class SaleUpdateEvent.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class SaleUpdateEvent extends SaleCreateEvent
{
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
    public function __construct(protected $saleId)
    {
    }

    /**
     * @param int $saleId
     *
     * @return SaleUpdateEvent $this
     */
    public function setSaleId($saleId): static
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
    public function setChapo($chapo): static
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
    public function setDescription($description): static
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
    public function setPostscriptum($postscriptum): static
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
     * @param bool $active
     *
     * @return SaleUpdateEvent $this
     */
    public function setActive($active): static
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
     * @param mixed $endDate string, integer (timestamp), or \DateTime value
     *
     * @return SaleUpdateEvent $this
     */
    public function setEndDate($endDate): static
    {
        $this->endDate = PropelDateTime::newInstance($endDate, null, '\DateTime');

        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * @param int $priceOffsetType
     *
     * @return SaleUpdateEvent $this
     */
    public function setPriceOffsetType($priceOffsetType): static
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
     * @param mixed $startDate string, integer (timestamp), or \DateTime value
     *
     * @return SaleUpdateEvent $this
     */
    public function setStartDate($startDate): static
    {
        $this->startDate = PropelDateTime::newInstance($startDate, null, '\DateTime');

        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @param bool $displayInitialPrice
     *
     * @return $this
     */
    public function setDisplayInitialPrice($displayInitialPrice): static
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
     * @param array $priceOffsets an array of (currency_id => price offset) couples
     *
     * @return $this
     */
    public function setPriceOffsets($priceOffsets): static
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
     * @param array $products an array of product ID
     *
     * @return $this
     */
    public function setProducts($products): static
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
     * @param array $productAttributes an array of (product_id => array of attribute IDs)
     *
     * @return $this
     */
    public function setProductAttributes($productAttributes): static
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
